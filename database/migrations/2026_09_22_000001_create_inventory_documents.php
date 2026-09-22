<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_documents', function (Blueprint $table): void {
            $table->id();
            $table->string('direction', 20)->index();
            $table->string('document_number', 100)->unique();
            $table->date('document_date')->index();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('revision')->default(1);
            $table->timestamp('posted_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('memo')->nullable();
            $table->timestamps();

            $table->index(['direction', 'document_date'], 'inventory_documents_direction_date_index');
        });

        Schema::create('inventory_document_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_location_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('location_key', 100);
            $table->decimal('quantity', 18, 6);
            $table->timestamps();

            $table->unique(
                ['inventory_document_id', 'part_id', 'location_key'],
                'inventory_document_items_part_location_unique',
            );
        });

        Schema::create('inventory_document_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->json('snapshot');
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['inventory_document_id', 'revision'],
                'inventory_document_revisions_document_revision_unique',
            );
        });

        Schema::table('inventory_transactions', function (Blueprint $table): void {
            $table->foreignId('inventory_document_id')
                ->nullable()
                ->after('inventory_id')
                ->constrained()
                ->restrictOnDelete();
            $table->unsignedInteger('document_revision')->nullable()->after('inventory_document_id');
            $table->index(
                ['inventory_document_id', 'document_revision'],
                'inventory_tx_document_revision_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table): void {
            $table->dropIndex('inventory_tx_document_revision_index');
            $table->dropConstrainedForeignId('inventory_document_id');
            $table->dropColumn('document_revision');
        });

        Schema::dropIfExists('inventory_document_revisions');
        Schema::dropIfExists('inventory_document_items');
        Schema::dropIfExists('inventory_documents');
    }
};
