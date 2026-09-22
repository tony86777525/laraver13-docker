<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('part_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('location_key', 100);
            $table->decimal('quantity', 18, 6)->default(0);
            $table->timestamps();

            $table->unique(['part_id', 'warehouse_id', 'location_key'], 'inventories_part_warehouse_location_unique');
            $table->index(['warehouse_id', 'warehouse_location_id'], 'inventories_warehouse_location_index');
        });

        Schema::create('inventory_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('part_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('transaction_type', 50)->index();
            $table->decimal('before_quantity', 18, 6);
            $table->decimal('quantity_delta', 18, 6);
            $table->decimal('after_quantity', 18, 6);
            $table->string('reference_type', 100)->nullable()->index();
            $table->string('reference_number', 100)->nullable()->index();
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at')->index();
            $table->text('memo')->nullable();
            $table->timestamps();

            $table->index(['part_id', 'warehouse_id', 'occurred_at'], 'inventory_tx_part_warehouse_time_index');
            $table->index(['warehouse_id', 'warehouse_location_id', 'occurred_at'], 'inventory_tx_warehouse_location_time_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transactions');
        Schema::dropIfExists('inventories');
    }
};
