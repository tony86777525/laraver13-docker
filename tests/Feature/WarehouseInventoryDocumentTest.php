<?php

namespace Tests\Feature;

use App\Filament\Resources\InboundDocuments\InboundDocumentResource;
use App\Filament\Resources\InboundDocuments\Pages\CreateInboundDocument;
use App\Filament\Resources\InboundDocuments\Pages\EditInboundDocument;
use App\Filament\Resources\InboundDocuments\Pages\ListInboundDocuments;
use App\Filament\Resources\InboundDocuments\Pages\ViewInboundDocument;
use App\Filament\Resources\OutboundDocuments\OutboundDocumentResource;
use App\Models\InboundDocument;
use App\Models\Inventory;
use App\Models\InventoryDocument;
use App\Models\InventoryTransaction;
use App\Models\Part;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Services\InventoryAdjustmentService;
use App\Services\InventoryDocumentService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class WarehouseInventoryDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_inbound_document_posts_multiple_items_atomically(): void
    {
        [$warehouse, $location, $partOne, $partTwo] = $this->masterData();
        $operator = User::factory()->create();

        $document = app(InventoryDocumentService::class)->create(
            InventoryDocument::DIRECTION_INBOUND,
            $this->documentData('IN-001', $warehouse, [
                ['part_id' => $partOne->id, 'warehouse_location_id' => $location->id, 'quantity' => '10.25'],
                ['part_id' => $partTwo->id, 'warehouse_location_id' => null, 'quantity' => '3'],
            ]),
            $operator,
        );

        $this->assertSame(2, $document->items()->count());
        $this->assertSame(1, $document->revisions()->count());
        $this->assertSame(2, $document->transactions()->count());
        $this->assertDatabaseHas('inventories', [
            'part_id' => $partOne->id,
            'warehouse_id' => $warehouse->id,
            'warehouse_location_id' => $location->id,
            'quantity' => 10.25,
        ]);
        $this->assertDatabaseHas('inventories', [
            'part_id' => $partTwo->id,
            'warehouse_id' => $warehouse->id,
            'location_key' => Inventory::NO_LOCATION_KEY,
            'quantity' => 3,
        ]);
    }

    public function test_outbound_document_rolls_back_when_any_item_is_short(): void
    {
        [$warehouse, $location, $partOne, $partTwo] = $this->masterData();
        $service = app(InventoryDocumentService::class);
        $service->create(
            InventoryDocument::DIRECTION_INBOUND,
            $this->documentData('IN-001', $warehouse, [
                ['part_id' => $partOne->id, 'warehouse_location_id' => $location->id, 'quantity' => 5],
                ['part_id' => $partTwo->id, 'warehouse_location_id' => null, 'quantity' => 1],
            ]),
        );

        try {
            $service->create(
                InventoryDocument::DIRECTION_OUTBOUND,
                $this->documentData('OUT-001', $warehouse, [
                    ['part_id' => $partOne->id, 'warehouse_location_id' => $location->id, 'quantity' => 2],
                    ['part_id' => $partTwo->id, 'warehouse_location_id' => null, 'quantity' => 2],
                ]),
            );
            $this->fail('Expected inventory validation failure.');
        } catch (ValidationException) {
            $this->assertDatabaseMissing('inventory_documents', ['document_number' => 'OUT-001']);
            $this->assertDatabaseHas('inventories', [
                'part_id' => $partOne->id,
                'quantity' => 5,
            ]);
            $this->assertDatabaseHas('inventories', [
                'part_id' => $partTwo->id,
                'quantity' => 1,
            ]);
        }
    }

    public function test_outbound_document_can_create_negative_inventory_when_enabled(): void
    {
        config(['warehouse.allow_negative_inventory' => true]);
        [$warehouse, $location, $part] = $this->masterData();

        app(InventoryDocumentService::class)->create(
            InventoryDocument::DIRECTION_OUTBOUND,
            $this->documentData('OUT-NEGATIVE', $warehouse, [
                ['part_id' => $part->id, 'warehouse_location_id' => $location->id, 'quantity' => 2],
            ]),
        );

        $this->assertDatabaseHas('inventories', [
            'part_id' => $part->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => -2,
        ]);
    }

    public function test_document_update_moves_stock_and_keeps_revision_history(): void
    {
        [$warehouse, $location, $part] = $this->masterData();
        $otherWarehouse = Warehouse::query()->create(['code' => 'WH02', 'name' => 'Second']);
        $otherLocation = WarehouseLocation::query()->create(['warehouse_id' => $otherWarehouse->id, 'code' => 'B-01']);
        $service = app(InventoryDocumentService::class);
        $document = $service->create(
            InventoryDocument::DIRECTION_INBOUND,
            $this->documentData('IN-001', $warehouse, [
                ['part_id' => $part->id, 'warehouse_location_id' => $location->id, 'quantity' => 10],
            ]),
        );

        $service->update($document, $this->documentData('IN-001-UPDATED', $otherWarehouse, [
            ['part_id' => $part->id, 'warehouse_location_id' => $otherLocation->id, 'quantity' => 4],
        ]));

        $document->refresh();
        $this->assertSame(2, $document->revision);
        $this->assertSame(2, $document->revisions()->count());
        $this->assertSame(3, $document->transactions()->count());
        $this->assertDatabaseHas('inventories', [
            'part_id' => $part->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 0,
        ]);
        $this->assertDatabaseHas('inventories', [
            'part_id' => $part->id,
            'warehouse_id' => $otherWarehouse->id,
            'quantity' => 4,
        ]);
    }

    public function test_header_only_update_creates_revision_without_inventory_transaction(): void
    {
        [$warehouse, $location, $part] = $this->masterData();
        $service = app(InventoryDocumentService::class);
        $data = $this->documentData('IN-001', $warehouse, [
            ['part_id' => $part->id, 'warehouse_location_id' => $location->id, 'quantity' => 10],
        ]);
        $document = $service->create(InventoryDocument::DIRECTION_INBOUND, $data);

        $service->update($document, [...$data, 'memo' => 'Updated memo']);

        $document->refresh();
        $this->assertSame(2, $document->revision);
        $this->assertSame(2, $document->revisions()->count());
        $this->assertSame(1, $document->transactions()->count());
    }

    public function test_document_number_is_globally_unique_and_duplicate_lines_are_rejected(): void
    {
        [$warehouse, $location, $part] = $this->masterData();
        $service = app(InventoryDocumentService::class);
        $service->create(
            InventoryDocument::DIRECTION_INBOUND,
            $this->documentData('DOC-001', $warehouse, [
                ['part_id' => $part->id, 'warehouse_location_id' => $location->id, 'quantity' => 2],
            ]),
        );

        try {
            $service->create(
                InventoryDocument::DIRECTION_OUTBOUND,
                $this->documentData('DOC-001', $warehouse, [
                    ['part_id' => $part->id, 'warehouse_location_id' => $location->id, 'quantity' => 1],
                ]),
            );
            $this->fail('Expected duplicate document number validation failure.');
        } catch (ValidationException) {
            $this->assertSame(1, InventoryDocument::query()->count());
        }

        $this->expectException(ValidationException::class);
        $service->create(
            InventoryDocument::DIRECTION_INBOUND,
            $this->documentData('DOC-002', $warehouse, [
                ['part_id' => $part->id, 'warehouse_location_id' => $location->id, 'quantity' => 1],
                ['part_id' => $part->id, 'warehouse_location_id' => $location->id, 'quantity' => 2],
            ]),
        );
    }

    public function test_manual_adjustment_rejects_inbound_and_outbound_types(): void
    {
        [$warehouse, $location, $part] = $this->masterData();

        $this->expectException(ValidationException::class);
        app(InventoryAdjustmentService::class)->adjust([
            'part_id' => $part->id,
            'warehouse_id' => $warehouse->id,
            'warehouse_location_id' => $location->id,
            'quantity_delta' => 1,
            'transaction_type' => InventoryTransaction::TYPE_INBOUND,
        ]);
    }

    public function test_inbound_and_outbound_resources_use_separate_permissions(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ([
            'ViewAny:InboundDocument',
            'Create:InboundDocument',
            'ViewAny:OutboundDocument',
            'Create:OutboundDocument',
        ] as $permission) {
            Permission::query()->create(['name' => $permission, 'guard_name' => 'web']);
        }

        $user = User::factory()->create();
        $user->givePermissionTo(['ViewAny:InboundDocument', 'Create:InboundDocument']);
        $this->actingAs($user);

        $this->assertTrue(InboundDocumentResource::canViewAny());
        $this->assertTrue(InboundDocumentResource::canCreate());
        $this->assertFalse(OutboundDocumentResource::canViewAny());
        $this->assertFalse(OutboundDocumentResource::canCreate());
    }

    public function test_filament_inbound_pages_render_and_create_document(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['ViewAny:InboundDocument', 'Create:InboundDocument', 'View:InboundDocument', 'Update:InboundDocument'] as $permission) {
            Permission::query()->create(['name' => $permission, 'guard_name' => 'web']);
        }

        $user = User::factory()->create();
        $user->givePermissionTo(['ViewAny:InboundDocument', 'Create:InboundDocument', 'View:InboundDocument', 'Update:InboundDocument']);
        [$warehouse, $location, $part] = $this->masterData();
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ListInboundDocuments::class)->assertSuccessful();
        Livewire::test(CreateInboundDocument::class)
            ->fillForm($this->documentData('IN-LIVEWIRE', $warehouse, [
                ['part_id' => $part->id, 'warehouse_location_id' => $location->id, 'quantity' => 7],
            ]))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('inventory_documents', [
            'direction' => InventoryDocument::DIRECTION_INBOUND,
            'document_number' => 'IN-LIVEWIRE',
        ]);

        $document = InboundDocument::query()->where('document_number', 'IN-LIVEWIRE')->firstOrFail();
        Livewire::test(ViewInboundDocument::class, ['record' => $document->getRouteKey()])
            ->assertSuccessful();
        Livewire::test(EditInboundDocument::class, ['record' => $document->getRouteKey()])
            ->fillForm($this->documentData('IN-LIVEWIRE-EDITED', $warehouse, [
                ['part_id' => $part->id, 'warehouse_location_id' => $location->id, 'quantity' => 8],
            ]))
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('inventory_documents', [
            'id' => $document->id,
            'document_number' => 'IN-LIVEWIRE-EDITED',
            'revision' => 2,
        ]);
    }

    /** @return array{Warehouse, WarehouseLocation, Part, Part} */
    private function masterData(): array
    {
        $warehouse = Warehouse::query()->create(['code' => 'WH01', 'name' => 'Main']);
        $location = WarehouseLocation::query()->create(['warehouse_id' => $warehouse->id, 'code' => 'A-01']);
        $partOne = Part::query()->create([
            'part_number' => 'P-001',
            'name' => 'Part One',
            'is_stock_calculated' => false,
            'is_disabled' => true,
        ]);
        $partTwo = Part::query()->create([
            'part_number' => 'P-002',
            'name' => 'Part Two',
            'is_stock_calculated' => true,
            'is_disabled' => false,
        ]);

        return [$warehouse, $location, $partOne, $partTwo];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function documentData(string $number, Warehouse $warehouse, array $items): array
    {
        return [
            'document_number' => $number,
            'document_date' => '2026-09-22',
            'warehouse_id' => $warehouse->id,
            'memo' => null,
            'items' => $items,
        ];
    }
}
