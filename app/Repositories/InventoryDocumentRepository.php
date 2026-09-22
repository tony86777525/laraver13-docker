<?php

namespace App\Repositories;

use App\Models\Inventory;
use App\Models\InventoryDocument;
use App\Models\InventoryDocumentItem;
use App\Models\User;
use Illuminate\Support\Carbon;

class InventoryDocumentRepository
{
    public function lock(InventoryDocument $document): InventoryDocument
    {
        return InventoryDocument::query()
            ->whereKey($document->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * @param  array<int, array{part_id:int, warehouse_location_id:int|null, quantity:string}>  $items
     */
    public function replaceItems(InventoryDocument $document, array $items): void
    {
        $document->items()->delete();

        $document->items()->createMany(array_map(
            fn (array $item): array => [
                ...$item,
                'location_key' => Inventory::locationKey($item['warehouse_location_id']),
            ],
            $items,
        ));
    }

    /**
     * @param  array<int, array{part_id:int, warehouse_location_id:int|null, quantity:string}>  $items
     */
    public function createRevision(InventoryDocument $document, array $items, ?User $operator): void
    {
        $document->revisions()->create([
            'revision' => $document->revision,
            'snapshot' => [
                'direction' => $document->direction,
                'document_number' => $document->document_number,
                'document_date' => $document->document_date->toDateString(),
                'warehouse_id' => $document->warehouse_id,
                'memo' => $document->memo,
                'items' => $items,
            ],
            'operator_id' => $operator?->id,
            'created_at' => Carbon::now(),
        ]);
    }

    /**
     * @return array<int, array{part_id:int, warehouse_location_id:int|null, quantity:string}>
     */
    public function itemData(InventoryDocument $document): array
    {
        return $document->items()
            ->orderBy('id')
            ->get()
            ->map(fn (InventoryDocumentItem $item): array => [
                'part_id' => $item->part_id,
                'warehouse_location_id' => $item->warehouse_location_id,
                'quantity' => $item->quantity,
            ])
            ->all();
    }
}
