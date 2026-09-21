<?php

namespace App\Filament\Resources\Parts\Pages;

use App\Filament\Resources\Parts\PartResource;
use App\Services\PartImportService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ListParts extends ListRecords
{
    protected static string $resource = PartResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importWarehouseParts')
                ->label('Import XLSX')
                ->visible(fn (): bool => PartResource::canCreate())
                ->schema([
                    FileUpload::make('file')
                        ->label('Warehouse Sub-item Data List')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ])
                        ->storeFiles(false)
                        ->required(),
                ])
                ->action(function (array $data, PartImportService $importer): void {
                    $file = $data['file'];
                    $filePath = $file instanceof TemporaryUploadedFile
                        ? $file->getRealPath()
                        : Storage::disk('local')->path($file);

                    $batch = $importer->import(
                        $filePath,
                        $file instanceof TemporaryUploadedFile ? $file->getClientOriginalName() : basename((string) $file),
                        auth()->user(),
                        $file instanceof TemporaryUploadedFile ? null : (string) $file,
                    );

                    Notification::make()
                        ->title('Import finished')
                        ->body("Status: {$batch->status}; success: {$batch->successful_rows}; failed: {$batch->failed_rows}.")
                        ->success()
                        ->send();
                }),
        ];
    }
}
