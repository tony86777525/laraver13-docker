<?php

namespace App\Filament\Resources\InventoryDocuments\Pages;

use App\Models\InventoryDocument;
use App\Repositories\InventoryDocumentRepository;
use App\Services\InventoryDocumentService;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

abstract class EditInventoryDocument extends EditRecord
{
    /** @param array<string, mixed> $data */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var InventoryDocument $record */
        $record = $this->getRecord();
        $data['items'] = app(InventoryDocumentRepository::class)->itemData($record);

        return $data;
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var InventoryDocument $record */
        $document = app(InventoryDocumentService::class)->update($record, $data, auth()->user());
        $model = static::getResource()::getModel();

        return $model::query()->findOrFail($document->id);
    }

    protected function getHeaderActions(): array
    {
        return [ViewAction::make()];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }
}
