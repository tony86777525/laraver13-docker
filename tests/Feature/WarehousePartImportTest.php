<?php

namespace Tests\Feature;

use App\Filament\Resources\ImportBatches\ImportBatchResource;
use App\Filament\Resources\Parts\PartResource;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Filament\Resources\Warehouses\WarehouseResource;
use App\Models\ImportBatch;
use App\Models\ImportBatchRow;
use App\Models\Part;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Repositories\PartRepository;
use App\Services\PartImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class WarehousePartImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_excel_imports_parts_and_related_master_data(): void
    {
        $path = $this->writeWorkbook([
            $this->validRow([
                '品號' => 'P-001',
                '品名' => 'Washer',
                '計算庫存' => 'T',
                '主要倉庫' => 'WHPT',
                '庫別名稱' => '物料倉',
                '主供應商' => 'TW007',
                '廠商簡稱' => '朝昶',
                '儲位' => 'A-01',
                '失效' => 'F',
                '依需求補貨' => 'T',
                '安全存量' => 0,
                '最近進價' => 0.15,
                '單位淨重' => '',
            ]),
            $this->validRow([
                '品號' => 'P-002',
                '品名' => 'Nut',
                '主要倉庫' => 'WHPT',
                '庫別名稱' => '物料倉',
                '主供應商' => 'TW008',
                '廠商簡稱' => '另一家',
                '儲位' => '',
                '最近進價' => 0,
            ]),
        ]);

        $batch = app(PartImportService::class)->import($path, 'parts.xlsx');

        $this->assertSame(ImportBatch::STATUS_COMPLETED, $batch->status);
        $this->assertSame(2, $batch->successful_rows);
        $this->assertSame(0, $batch->failed_rows);
        $this->assertDatabaseCount('parts', 2);
        $this->assertDatabaseHas('warehouses', ['code' => 'WHPT', 'name' => '物料倉']);
        $this->assertDatabaseHas('suppliers', ['code' => 'TW007', 'short_name' => '朝昶']);
        $this->assertDatabaseHas('warehouse_locations', ['code' => 'A-01']);

        $part = Part::query()->where('part_number', 'P-001')->firstOrFail();

        $this->assertTrue($part->is_stock_calculated);
        $this->assertFalse($part->is_disabled);
        $this->assertTrue($part->is_replenished_on_demand);
        $this->assertSame('0.000000', $part->safety_stock);
        $this->assertSame('0.150000', $part->recent_purchase_price);
        $this->assertNull($part->unit_net_weight);
    }

    public function test_duplicate_part_number_is_updated_instead_of_duplicated(): void
    {
        $first = $this->writeWorkbook([
            $this->validRow(['品號' => 'P-001', '品名' => 'Old Name', '最近進價' => 1]),
        ]);

        $second = $this->writeWorkbook([
            $this->validRow(['品號' => 'P-001', '品名' => 'New Name', '最近進價' => 2]),
        ]);

        app(PartImportService::class)->import($first, 'first.xlsx');
        app(PartImportService::class)->import($second, 'second.xlsx');

        $this->assertDatabaseCount('parts', 1);
        $this->assertDatabaseHas('parts', [
            'part_number' => 'P-001',
            'name' => 'New Name',
            'recent_purchase_price' => 2,
        ]);
    }

    public function test_duplicate_part_numbers_in_one_file_import_to_unique_part_count(): void
    {
        $path = $this->writeWorkbook([
            $this->validRow(['品號' => 'P-001', '品名' => 'Original Name']),
            $this->validRow(['品號' => 'P-002', '品名' => 'Second Part']),
            $this->validRow(['品號' => 'P-001', '品名' => 'Updated Name']),
        ]);

        $batch = app(PartImportService::class)->import($path, 'duplicates.xlsx');

        $this->assertSame(ImportBatch::STATUS_COMPLETED, $batch->status);
        $this->assertSame(3, $batch->successful_rows);
        $this->assertDatabaseCount('parts', 2);
        $this->assertDatabaseHas('parts', [
            'part_number' => 'P-001',
            'name' => 'Updated Name',
        ]);
    }

    public function test_part_master_search_can_filter_by_location(): void
    {
        $path = $this->writeWorkbook([
            $this->validRow([
                '品號' => 'P-001',
                '品名' => 'Washer',
                '主要倉庫' => 'WHPT',
                '儲位' => 'A-01',
            ]),
            $this->validRow([
                '品號' => 'P-002',
                '品名' => 'Nut',
                '主要倉庫' => 'WHPT',
                '儲位' => 'B-02',
            ]),
        ]);

        app(PartImportService::class)->import($path, 'locations.xlsx');

        $location = WarehouseLocation::query()->where('code', 'A-01')->firstOrFail();
        $parts = app(PartRepository::class)
            ->queryForMasterSearch(['location_id' => $location->id])
            ->pluck('part_number')
            ->all();

        $this->assertSame(['P-001'], $parts);
    }

    public function test_invalid_header_marks_batch_failed(): void
    {
        $path = $this->writeWorkbook([
            $this->validRow(['品號' => 'P-001']),
        ], ['WRONG', ...array_slice(PartImportService::EXPECTED_HEADERS, 1)]);

        $batch = app(PartImportService::class)->import($path, 'bad-header.xlsx');

        $this->assertSame(ImportBatch::STATUS_FAILED, $batch->status);
        $this->assertSame(0, $batch->total_rows);
        $this->assertDatabaseCount('parts', 0);
        $this->assertStringContainsString('表頭', (string) $batch->error_message);
    }

    public function test_single_row_error_is_recorded_without_stopping_batch(): void
    {
        $path = $this->writeWorkbook([
            $this->validRow(['品號' => 'P-001']),
            $this->validRow(['品號' => 'P-002', '計算庫存' => 'X']),
        ]);

        $batch = app(PartImportService::class)->import($path, 'row-error.xlsx');

        $this->assertSame(ImportBatch::STATUS_COMPLETED_WITH_ERRORS, $batch->status);
        $this->assertSame(2, $batch->total_rows);
        $this->assertSame(1, $batch->successful_rows);
        $this->assertSame(1, $batch->failed_rows);
        $this->assertDatabaseHas('parts', ['part_number' => 'P-001']);
        $this->assertDatabaseMissing('parts', ['part_number' => 'P-002']);
        $this->assertDatabaseHas('import_batch_rows', [
            'row_number' => 3,
            'status' => ImportBatchRow::STATUS_FAILED,
        ]);

        $failed = ImportBatchRow::query()->where('status', ImportBatchRow::STATUS_FAILED)->firstOrFail();

        $this->assertSame('P-002', $failed->raw_payload['品號']);
        $this->assertStringContainsString('計算庫存', (string) $failed->error_message);
    }

    public function test_warehouse_permissions_control_viewing_and_importing(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::query()->create(['name' => 'ViewAny:Part', 'guard_name' => 'web']);
        Permission::query()->create(['name' => 'Create:Part', 'guard_name' => 'web']);

        $viewer = User::factory()->create();
        $viewer->givePermissionTo('ViewAny:Part');

        $manager = User::factory()->create();
        $manager->givePermissionTo(['ViewAny:Part', 'Create:Part']);

        $this->actingAs($viewer);

        $this->assertTrue(Gate::allows('viewAny', Part::class));
        $this->assertFalse(Gate::allows('create', Part::class));
        $this->assertFalse(PartResource::canCreate());

        $this->actingAs($manager);

        $this->assertTrue(Gate::allows('viewAny', Part::class));
        $this->assertTrue(Gate::allows('create', Part::class));
        $this->assertTrue(PartResource::canCreate());
    }

    public function test_warehouse_supplier_and_import_batch_permissions_use_resource_permissions(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ([
            'ViewAny:Warehouse',
            'Create:Warehouse',
            'Update:Warehouse',
            'Delete:Warehouse',
            'ViewAny:Supplier',
            'Create:Supplier',
            'Update:Supplier',
            'Delete:Supplier',
            'ViewAny:ImportBatch',
            'View:ImportBatch',
        ] as $permission) {
            Permission::query()->create(['name' => $permission, 'guard_name' => 'web']);
        }

        $viewer = User::factory()->create();
        $viewer->givePermissionTo(['ViewAny:Warehouse', 'ViewAny:Supplier', 'ViewAny:ImportBatch']);

        $manager = User::factory()->create();
        $manager->givePermissionTo([
            'ViewAny:Warehouse',
            'Create:Warehouse',
            'Update:Warehouse',
            'Delete:Warehouse',
            'ViewAny:Supplier',
            'Create:Supplier',
            'Update:Supplier',
            'Delete:Supplier',
            'ViewAny:ImportBatch',
            'View:ImportBatch',
        ]);

        $warehouse = Warehouse::query()->create(['code' => 'WHPT', 'name' => '物料倉']);
        $supplier = Supplier::query()->create(['code' => 'TW007', 'short_name' => '朝昶']);
        $batch = ImportBatch::query()->create([
            'import_type' => ImportBatch::TYPE_WAREHOUSE_PARTS,
            'status' => ImportBatch::STATUS_COMPLETED,
        ]);

        $this->actingAs($viewer);

        $this->assertTrue(Gate::allows('viewAny', Warehouse::class));
        $this->assertFalse(Gate::allows('create', Warehouse::class));
        $this->assertFalse(Gate::allows('update', $warehouse));
        $this->assertFalse(Gate::allows('delete', $warehouse));
        $this->assertFalse(WarehouseResource::canCreate());

        $this->assertTrue(Gate::allows('viewAny', Supplier::class));
        $this->assertFalse(Gate::allows('create', Supplier::class));
        $this->assertFalse(Gate::allows('update', $supplier));
        $this->assertFalse(Gate::allows('delete', $supplier));
        $this->assertFalse(SupplierResource::canCreate());

        $this->assertTrue(Gate::allows('viewAny', ImportBatch::class));
        $this->assertFalse(Gate::allows('view', $batch));
        $this->assertFalse(Gate::allows('create', ImportBatch::class));

        $this->actingAs($manager);

        $this->assertTrue(Gate::allows('viewAny', Warehouse::class));
        $this->assertTrue(Gate::allows('create', Warehouse::class));
        $this->assertTrue(Gate::allows('update', $warehouse));
        $this->assertTrue(Gate::allows('delete', $warehouse));
        $this->assertTrue(WarehouseResource::canCreate());

        $this->assertTrue(Gate::allows('viewAny', Supplier::class));
        $this->assertTrue(Gate::allows('create', Supplier::class));
        $this->assertTrue(Gate::allows('update', $supplier));
        $this->assertTrue(Gate::allows('delete', $supplier));
        $this->assertTrue(SupplierResource::canCreate());

        $this->assertTrue(Gate::allows('viewAny', ImportBatch::class));
        $this->assertTrue(Gate::allows('view', $batch));
        $this->assertFalse(Gate::allows('create', ImportBatch::class));
        $this->assertFalse(ImportBatchResource::canCreate());
    }

    public function test_super_admin_role_bypasses_warehouse_resource_policies(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $superAdmin = User::factory()->create();

        Role::query()->create(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdmin->assignRole('super_admin');

        $this->actingAs($superAdmin);

        $this->assertTrue(Gate::allows('viewAny', Part::class));
        $this->assertTrue(Gate::allows('create', Part::class));
        $this->assertTrue(PartResource::canCreate());
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>|null  $headers
     */
    private function writeWorkbook(array $rows, ?array $headers = null): string
    {
        $path = storage_path('framework/testing/warehouse-'.uniqid('', true).'.xlsx');

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($headers ?? PartImportService::EXPECTED_HEADERS));

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues(array_map(
                fn (string $header): mixed => $row[$header] ?? null,
                PartImportService::EXPECTED_HEADERS,
            )));
        }

        $writer->close();

        return $path;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validRow(array $overrides = []): array
    {
        return array_merge([
            '品號' => 'P-001',
            '品名' => 'Part Name',
            '條碼編號' => '',
            '庫存單位' => 'PCS',
            '商品分類' => 'RT',
            '會計分類' => 'RT',
            '計算庫存' => 'F',
            '主要倉庫' => 'WHPT',
            '庫別名稱' => '物料倉',
            '主要來源' => 'P',
            '主供應商' => 'TW007',
            '廠商簡稱' => '朝昶',
            '循環盤點碼' => '411',
            '儲位' => '',
            '失效' => 'F',
            '依需求補貨' => 'F',
            '前置天數' => 0,
            '安全存量' => 0,
            '最低補量' => 0,
            '補貨倍量' => 0,
            '標準進價' => 0,
            '最近進價' => 0,
            '零售價' => 0,
            '定價一' => 0,
            '定價二' => 0,
            '定價三' => 0,
            '定價四' => 0,
            '低階碼' => '1',
            '商品描述' => '',
            '英文品名' => '',
            '英文描述' => '',
            '進口關稅率' => 0,
            '單位淨重' => 0,
        ], $overrides);
    }
}
