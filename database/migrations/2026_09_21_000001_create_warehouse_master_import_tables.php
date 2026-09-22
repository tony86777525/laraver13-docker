<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('short_name')->nullable();
            $table->timestamps();
        });

        Schema::create('warehouse_locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->string('code', 100);
            $table->timestamps();

            $table->unique(['warehouse_id', 'code']);
        });

        Schema::create('parts', function (Blueprint $table): void {
            $table->id();
            $table->string('part_number')->unique();
            $table->string('name');
            $table->string('barcode')->nullable()->index();
            $table->string('stock_unit', 50)->nullable();
            $table->string('product_category', 100)->nullable()->index();
            $table->string('accounting_category', 100)->nullable()->index();
            $table->boolean('is_stock_calculated')->default(false)->index();
            $table->foreignId('primary_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->string('source_code', 20)->nullable();
            $table->foreignId('primary_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('cycle_count_code', 100)->nullable();
            $table->foreignId('primary_location_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $table->boolean('is_disabled')->default(false)->index();
            $table->boolean('is_replenished_on_demand')->default(false);
            $table->unsignedInteger('lead_days')->nullable();
            $table->decimal('safety_stock', 18, 6)->nullable();
            $table->decimal('minimum_replenishment_quantity', 18, 6)->nullable();
            $table->decimal('replenishment_multiple', 18, 6)->nullable();
            $table->decimal('standard_purchase_price', 18, 6)->nullable();
            $table->decimal('recent_purchase_price', 18, 6)->nullable();
            $table->decimal('retail_price', 18, 6)->nullable();
            $table->decimal('price_one', 18, 6)->nullable();
            $table->decimal('price_two', 18, 6)->nullable();
            $table->decimal('price_three', 18, 6)->nullable();
            $table->decimal('price_four', 18, 6)->nullable();
            $table->string('low_level_code', 100)->nullable();
            $table->text('description')->nullable();
            $table->string('english_name')->nullable();
            $table->text('english_description')->nullable();
            $table->decimal('import_tariff_rate', 9, 6)->nullable();
            $table->decimal('unit_net_weight', 18, 6)->nullable();
            $table->timestamps();

            $table->index(['part_number', 'name']);
        });

        Schema::create('import_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('import_type', 100);
            $table->string('source_filename')->nullable();
            $table->string('stored_path')->nullable();
            $table->string('status', 30)->default('pending')->index();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('successful_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('import_batch_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('import_batch_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('raw_payload');
            $table->string('status', 30)->index();
            $table->text('error_message')->nullable();
            $table->foreignId('part_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['import_batch_id', 'row_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batch_rows');
        Schema::dropIfExists('import_batches');
        Schema::dropIfExists('parts');
        Schema::dropIfExists('warehouse_locations');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('warehouses');
    }
};
