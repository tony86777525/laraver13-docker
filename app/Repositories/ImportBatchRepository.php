<?php

namespace App\Repositories;

use App\Models\ImportBatch;
use App\Models\ImportBatchRow;
use App\Models\Part;
use App\Models\User;
use Illuminate\Support\Carbon;

class ImportBatchRepository
{
    public function createWarehousePartBatch(?string $sourceFilename, ?string $storedPath, ?User $creator): ImportBatch
    {
        return ImportBatch::query()->create([
            'import_type' => ImportBatch::TYPE_WAREHOUSE_PARTS,
            'source_filename' => $sourceFilename,
            'stored_path' => $storedPath,
            'status' => ImportBatch::STATUS_PENDING,
            'created_by' => $creator?->id,
        ]);
    }

    public function markProcessing(ImportBatch $batch): void
    {
        $batch->forceFill([
            'status' => ImportBatch::STATUS_PROCESSING,
            'started_at' => Carbon::now(),
        ])->save();
    }

    public function recordSuccess(ImportBatch $batch, int $rowNumber, array $payload, Part $part): void
    {
        $batch->rows()->create([
            'row_number' => $rowNumber,
            'raw_payload' => $payload,
            'status' => ImportBatchRow::STATUS_SUCCESS,
            'part_id' => $part->id,
        ]);

        $batch->increment('total_rows');
        $batch->increment('successful_rows');
    }

    public function recordFailure(ImportBatch $batch, int $rowNumber, array $payload, string $errorMessage): void
    {
        $batch->rows()->create([
            'row_number' => $rowNumber,
            'raw_payload' => $payload,
            'status' => ImportBatchRow::STATUS_FAILED,
            'error_message' => $errorMessage,
        ]);

        $batch->increment('total_rows');
        $batch->increment('failed_rows');
    }

    public function markFinished(ImportBatch $batch): ImportBatch
    {
        $batch->refresh();

        $batch->forceFill([
            'status' => $batch->failed_rows > 0
                ? ImportBatch::STATUS_COMPLETED_WITH_ERRORS
                : ImportBatch::STATUS_COMPLETED,
            'finished_at' => Carbon::now(),
        ])->save();

        return $batch->refresh();
    }

    public function markFailed(ImportBatch $batch, string $errorMessage): ImportBatch
    {
        $batch->forceFill([
            'status' => ImportBatch::STATUS_FAILED,
            'error_message' => $errorMessage,
            'finished_at' => Carbon::now(),
        ])->save();

        return $batch->refresh();
    }
}
