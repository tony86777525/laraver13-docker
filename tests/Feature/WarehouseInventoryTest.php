<?php

namespace Tests\Feature;

use App\Filament\Resources\Inventories\InventoryResource;
use App\Filament\Resources\InventoryTransactions\InventoryTransactionResource;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\Part;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Repositories\InventoryRepository;
use App\Repositories\InventoryTransactionRepository;
use App\Services\InventoryAdjustmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class WarehouseInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_adjustment_creates_inventory_snapshot_and_transaction(): void
    {
        [$part, $warehouse, $location] = $this->createInventoryMasterData();
        $operator = User::factory()->create();

        $transaction = app(InventoryAdjustmentService::class)->adjust([
            'part_id' => $part->id,
            'warehouse_id' => $warehouse->id,
            'warehouse_location_id' => $location->id,
            'quantity_delta' => 12.5,
            'transaction_type' => InventoryTransaction::TYPE_ADJUSTMENT,
            'reference_type' => 'manual',
            'reference_number' => 'ADJ-001',
            'memo' => 'Initial stock',
            'occurred_at' => Carbon::parse('2026-09-21 09:30:00'),
        ], $operator);

        $this->assertDatabaseHas('inventories', [
            'part_id' => $part->id,
            'warehouse_id' => $warehouse->id,
            'warehouse_location_id' => $location->id,
            'location_key' => (string) $location->id,
            'quantity' => 12.5,
        ]);

        $this->assertSame('0.000000', $transaction->before_quantity);
        $this->assertSame('12.500000', $transaction->quantity_delta);
        $this->assertSame('12.500000', $transaction->after_quantity);
        $this->assertSame($operator->id, $transaction->operator_id);
    }

    public function test_negative_adjustment_is_rejected_by_default(): void
    {
        [$part, $warehouse] = $this->createInventoryMasterData();

        $this->expectException(ValidationException::class);

        app(InventoryAdjustmentService::class)->adjust([
            'part_id' => $part->id,
            'warehouse_id' => $warehouse->id,
            'quantity_delta' => -1,
            'transaction_type' => InventoryTransaction::TYPE_ADJUSTMENT,
        ]);
    }

    public function test_negative_adjustment_can_be_enabled_by_config(): void
    {
        config(['warehouse.allow_negative_inventory' => true]);
        [$part, $warehouse] = $this->createInventoryMasterData();

        $transaction = app(InventoryAdjustmentService::class)->adjust([
            'part_id' => $part->id,
            'warehouse_id' => $warehouse->id,
            'quantity_delta' => -1,
            'transaction_type' => InventoryTransaction::TYPE_ADJUSTMENT,
        ]);

        $this->assertSame('-1.000000', $transaction->after_quantity);
        $this->assertDatabaseHas('inventories', [
            'part_id' => $part->id,
            'warehouse_id' => $warehouse->id,
            'location_key' => Inventory::NO_LOCATION_KEY,
            'quantity' => -1,
        ]);
    }

    public function test_location_must_belong_to_selected_warehouse(): void
    {
        [$part, $warehouse] = $this->createInventoryMasterData();
        $otherWarehouse = Warehouse::query()->create(['code' => 'WH02', 'name' => 'Second Warehouse']);
        $otherLocation = WarehouseLocation::query()->create(['warehouse_id' => $otherWarehouse->id, 'code' => 'B-01']);

        $this->expectException(ValidationException::class);

        app(InventoryAdjustmentService::class)->adjust([
            'part_id' => $part->id,
            'warehouse_id' => $warehouse->id,
            'warehouse_location_id' => $otherLocation->id,
            'quantity_delta' => 1,
            'transaction_type' => InventoryTransaction::TYPE_ADJUSTMENT,
        ]);
    }

    public function test_inventory_lookup_filters_by_master_data(): void
    {
        [$part, $warehouse, $location] = $this->createInventoryMasterData([
            'is_disabled' => true,
            'is_stock_calculated' => true,
        ]);

        app(InventoryAdjustmentService::class)->adjust([
            'part_id' => $part->id,
            'warehouse_id' => $warehouse->id,
            'warehouse_location_id' => $location->id,
            'quantity_delta' => 3,
            'transaction_type' => InventoryTransaction::TYPE_ADJUSTMENT,
        ]);

        $query = app(InventoryRepository::class)->queryForLookup([
            'search' => 'P-001',
            'part_id' => $part->id,
            'warehouse_id' => $warehouse->id,
            'location_id' => $location->id,
            'is_disabled' => true,
            'is_stock_calculated' => true,
        ]);

        $this->assertSame(1, $query->count());
    }

    public function test_transaction_history_filters_by_part_warehouse_type_date_and_reference(): void
    {
        [$part, $warehouse] = $this->createInventoryMasterData();

        app(InventoryAdjustmentService::class)->adjust([
            'part_id' => $part->id,
            'warehouse_id' => $warehouse->id,
            'quantity_delta' => 5,
            'transaction_type' => InventoryTransaction::TYPE_ADJUSTMENT,
            'reference_type' => 'manual',
            'reference_number' => 'ADJ-001',
            'occurred_at' => '2026-09-21 10:00:00',
        ]);

        $query = app(InventoryTransactionRepository::class)->queryForHistory([
            'part_id' => $part->id,
            'warehouse_id' => $warehouse->id,
            'transaction_type' => InventoryTransaction::TYPE_ADJUSTMENT,
            'reference' => 'ADJ-001',
            'occurred_from' => '2026-09-21',
            'occurred_until' => '2026-09-21',
        ]);

        $this->assertSame(1, $query->count());
    }

    public function test_inventory_permissions_use_resource_permissions(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ([
            'ViewAny:Inventory',
            'Create:Inventory',
            'Update:Inventory',
            'ViewAny:InventoryTransaction',
            'View:InventoryTransaction',
        ] as $permission) {
            Permission::query()->create(['name' => $permission, 'guard_name' => 'web']);
        }

        [$part, $warehouse] = $this->createInventoryMasterData();
        $transaction = app(InventoryAdjustmentService::class)->adjust([
            'part_id' => $part->id,
            'warehouse_id' => $warehouse->id,
            'quantity_delta' => 1,
            'transaction_type' => InventoryTransaction::TYPE_ADJUSTMENT,
        ]);
        $inventory = Inventory::query()->firstOrFail();

        $viewer = User::factory()->create();
        $viewer->givePermissionTo(['ViewAny:Inventory', 'ViewAny:InventoryTransaction']);

        $manager = User::factory()->create();
        $manager->givePermissionTo([
            'ViewAny:Inventory',
            'Create:Inventory',
            'Update:Inventory',
            'ViewAny:InventoryTransaction',
            'View:InventoryTransaction',
        ]);

        $this->actingAs($viewer);

        $this->assertTrue(Gate::allows('viewAny', Inventory::class));
        $this->assertFalse(Gate::allows('create', Inventory::class));
        $this->assertFalse(Gate::allows('update', $inventory));
        $this->assertFalse(InventoryResource::canCreate());
        $this->assertTrue(Gate::allows('viewAny', InventoryTransaction::class));
        $this->assertFalse(Gate::allows('view', $transaction));

        $this->actingAs($manager);

        $this->assertTrue(Gate::allows('viewAny', Inventory::class));
        $this->assertTrue(Gate::allows('create', Inventory::class));
        $this->assertTrue(Gate::allows('update', $inventory));
        $this->assertTrue(InventoryResource::canCreate());
        $this->assertTrue(Gate::allows('viewAny', InventoryTransaction::class));
        $this->assertTrue(Gate::allows('view', $transaction));
        $this->assertFalse(Gate::allows('create', InventoryTransaction::class));
        $this->assertFalse(InventoryTransactionResource::canCreate());
    }

    public function test_privileged_role_names_do_not_bypass_inventory_permissions(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ([
            'ViewAny:Inventory',
            'Create:Inventory',
            'Update:Inventory',
        ] as $permission) {
            Permission::query()->create(['name' => $permission, 'guard_name' => 'web']);
        }

        [$part, $warehouse] = $this->createInventoryMasterData();
        $inventory = Inventory::query()->create([
            'part_id' => $part->id,
            'warehouse_id' => $warehouse->id,
            'location_key' => Inventory::locationKey(null),
            'quantity' => 0,
        ]);

        foreach (['super_admin', 'admin'] as $roleName) {
            $role = Role::query()->create(['name' => $roleName, 'guard_name' => 'web']);
            $user = User::factory()->create();
            $user->assignRole($role);

            $this->actingAs($user);

            $this->assertFalse(Gate::allows('viewAny', Inventory::class));
            $this->assertFalse(Gate::allows('create', Inventory::class));
            $this->assertFalse(Gate::allows('update', $inventory));
            $this->assertFalse(InventoryResource::canCreate());

            $role->givePermissionTo([
                'ViewAny:Inventory',
                'Create:Inventory',
                'Update:Inventory',
            ]);

            $this->assertTrue(Gate::allows('viewAny', Inventory::class));
            $this->assertTrue(Gate::allows('create', Inventory::class));
            $this->assertTrue(Gate::allows('update', $inventory));
            $this->assertTrue(InventoryResource::canCreate());
        }
    }

    /**
     * @param  array<string, mixed>  $partOverrides
     * @return array{Part, Warehouse, WarehouseLocation}
     */
    private function createInventoryMasterData(array $partOverrides = []): array
    {
        $warehouse = Warehouse::query()->create(['code' => 'WH01', 'name' => 'Main Warehouse']);
        $location = WarehouseLocation::query()->create(['warehouse_id' => $warehouse->id, 'code' => 'A-01']);
        $part = Part::query()->create(array_merge([
            'part_number' => 'P-001',
            'name' => 'Part One',
            'stock_unit' => 'PCS',
            'is_stock_calculated' => false,
            'primary_warehouse_id' => $warehouse->id,
            'primary_location_id' => $location->id,
            'is_disabled' => false,
            'is_replenished_on_demand' => false,
        ], $partOverrides));

        return [$part, $warehouse, $location];
    }
}
