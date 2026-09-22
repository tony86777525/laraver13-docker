<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\InventoryDocument;
use App\Models\InventoryTransaction;
use App\Models\User;
use App\Models\WarehouseLocation;
use App\Repositories\InventoryDocumentRepository;
use App\Repositories\InventoryRepository;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventoryDocumentService
{
    public function __construct(
        private readonly InventoryDocumentRepository $documents,
        private readonly InventoryRepository $inventories,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(string $direction, array $data, ?User $operator = null): InventoryDocument
    {
        $data = $this->validatedData($data, $direction);

        return DB::transaction(function () use ($data, $direction, $operator): InventoryDocument {
            $document = InventoryDocument::query()->create([
                ...Arr::only($data, ['document_number', 'document_date', 'warehouse_id', 'memo']),
                'direction' => $direction,
                'revision' => 1,
                'posted_at' => Carbon::now(),
                'created_by' => $operator?->id,
                'updated_by' => $operator?->id,
            ]);

            $this->documents->replaceItems($document, $data['items']);
            $this->documents->createRevision($document, $data['items'], $operator);
            $this->applyMovementChanges(
                document: $document,
                oldWarehouseId: null,
                oldItems: [],
                newWarehouseId: $document->warehouse_id,
                newItems: $data['items'],
                operator: $operator,
            );

            return $document->load(['warehouse', 'items.part', 'items.warehouseLocation']);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(InventoryDocument $document, array $data, ?User $operator = null): InventoryDocument
    {
        $data = $this->validatedData($data, $document->direction, $document);

        return DB::transaction(function () use ($document, $data, $operator): InventoryDocument {
            $document = $this->documents->lock($document);
            $oldWarehouseId = $document->warehouse_id;
            $oldItems = $this->documents->itemData($document);
            $nextRevision = $document->revision + 1;

            $this->applyMovementChanges(
                document: $document,
                oldWarehouseId: $oldWarehouseId,
                oldItems: $oldItems,
                newWarehouseId: $data['warehouse_id'],
                newItems: $data['items'],
                operator: $operator,
                revision: $nextRevision,
                referenceNumber: $data['document_number'],
            );

            $document->update([
                ...Arr::only($data, ['document_number', 'document_date', 'warehouse_id', 'memo']),
                'revision' => $nextRevision,
                'updated_by' => $operator?->id,
            ]);

            $this->documents->replaceItems($document, $data['items']);
            $this->documents->createRevision($document, $data['items'], $operator);

            return $document->load(['warehouse', 'items.part', 'items.warehouseLocation']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{document_number:string, document_date:string, warehouse_id:int, memo:string|null, items:array<int, array{part_id:int, warehouse_location_id:int|null, quantity:string}>}
     */
    private function validatedData(array $data, string $direction, ?InventoryDocument $document = null): array
    {
        if (! in_array($direction, [InventoryDocument::DIRECTION_INBOUND, InventoryDocument::DIRECTION_OUTBOUND], true)) {
            throw ValidationException::withMessages(['direction' => 'Invalid inventory document direction.']);
        }

        $data['document_number'] = trim((string) ($data['document_number'] ?? ''));

        $validated = Validator::make($data, [
            'document_number' => [
                'required',
                'string',
                'max:100',
                Rule::unique('inventory_documents', 'document_number')->ignore($document?->id),
            ],
            'document_date' => ['required', 'date'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'memo' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.part_id' => ['required', 'integer', 'exists:parts,id'],
            'items.*.warehouse_location_id' => ['nullable', 'integer', 'exists:warehouse_locations,id'],
            'items.*.quantity' => ['required', 'numeric', 'decimal:0,6', 'gt:0', 'max:999999999999.999999'],
        ])->validate();

        $items = [];
        $seen = [];

        foreach ($validated['items'] as $index => $item) {
            $locationId = filled($item['warehouse_location_id'] ?? null)
                ? (int) $item['warehouse_location_id']
                : null;

            if ($locationId !== null && ! WarehouseLocation::query()
                ->whereKey($locationId)
                ->where('warehouse_id', $validated['warehouse_id'])
                ->exists()) {
                throw ValidationException::withMessages([
                    "items.{$index}.warehouse_location_id" => '儲位必須屬於選定倉庫。',
                ]);
            }

            $key = $item['part_id'].'|'.Inventory::locationKey($locationId);

            if (isset($seen[$key])) {
                throw ValidationException::withMessages([
                    "items.{$index}.part_id" => '同一子料與儲位不可重複。',
                ]);
            }

            $seen[$key] = true;
            $items[] = [
                'part_id' => (int) $item['part_id'],
                'warehouse_location_id' => $locationId,
                'quantity' => $this->normalizeDecimal($item['quantity']),
            ];
        }

        return [
            'document_number' => trim($validated['document_number']),
            'document_date' => Carbon::parse($validated['document_date'])->toDateString(),
            'warehouse_id' => (int) $validated['warehouse_id'],
            'memo' => $this->nullableString($validated['memo'] ?? null),
            'items' => $items,
        ];
    }

    /**
     * @param  array<int, array{part_id:int, warehouse_location_id:int|null, quantity:string}>  $oldItems
     * @param  array<int, array{part_id:int, warehouse_location_id:int|null, quantity:string}>  $newItems
     */
    private function applyMovementChanges(
        InventoryDocument $document,
        ?int $oldWarehouseId,
        array $oldItems,
        int $newWarehouseId,
        array $newItems,
        ?User $operator,
        ?int $revision = null,
        ?string $referenceNumber = null,
    ): void {
        $revision ??= $document->revision;
        $referenceNumber ??= $document->document_number;
        $old = $oldWarehouseId === null ? [] : $this->contributions($document->direction, $oldWarehouseId, $oldItems);
        $new = $this->contributions($document->direction, $newWarehouseId, $newItems);
        $keys = array_unique([...array_keys($old), ...array_keys($new)]);
        sort($keys, SORT_STRING);

        foreach ($keys as $key) {
            $movement = $new[$key] ?? $old[$key];
            $delta = bcsub($new[$key]['quantity'] ?? '0.000000', $old[$key]['quantity'] ?? '0.000000', 6);

            if (bccomp($delta, '0.000000', 6) === 0) {
                continue;
            }

            $inventory = $this->inventories->firstOrCreateSnapshot(
                $movement['part_id'],
                $movement['warehouse_id'],
                $movement['warehouse_location_id'],
            );
            $inventory = $this->inventories->lockSnapshot($inventory);
            $before = $this->normalizeDecimal($inventory->quantity);
            $after = bcadd($before, $delta, 6);

            if (! config('warehouse.allow_negative_inventory') && bccomp($after, '0.000000', 6) < 0) {
                throw ValidationException::withMessages([
                    'items' => '庫存不足，整張單據無法過帳。',
                ]);
            }

            InventoryTransaction::query()->create([
                'inventory_id' => $inventory->id,
                'inventory_document_id' => $document->id,
                'document_revision' => $revision,
                'part_id' => $movement['part_id'],
                'warehouse_id' => $movement['warehouse_id'],
                'warehouse_location_id' => $movement['warehouse_location_id'],
                'transaction_type' => $document->direction,
                'before_quantity' => $before,
                'quantity_delta' => $delta,
                'after_quantity' => $after,
                'reference_type' => 'inventory_document',
                'reference_number' => $referenceNumber,
                'operator_id' => $operator?->id,
                'occurred_at' => Carbon::now(),
                'memo' => $revision === 1 ? 'Document posting' : "Document correction revision {$revision}",
            ]);

            $inventory->forceFill(['quantity' => $after])->save();
        }
    }

    /**
     * @param  array<int, array{part_id:int, warehouse_location_id:int|null, quantity:string}>  $items
     * @return array<string, array{part_id:int, warehouse_id:int, warehouse_location_id:int|null, quantity:string}>
     */
    private function contributions(string $direction, int $warehouseId, array $items): array
    {
        $multiplier = $direction === InventoryDocument::DIRECTION_INBOUND ? '1' : '-1';
        $contributions = [];

        foreach ($items as $item) {
            $key = $item['part_id'].'|'.$warehouseId.'|'.Inventory::locationKey($item['warehouse_location_id']);
            $contributions[$key] = [
                'part_id' => $item['part_id'],
                'warehouse_id' => $warehouseId,
                'warehouse_location_id' => $item['warehouse_location_id'],
                'quantity' => bcmul($item['quantity'], $multiplier, 6),
            ];
        }

        return $contributions;
    }

    private function normalizeDecimal(mixed $value): string
    {
        return bcadd((string) $value, '0', 6);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
