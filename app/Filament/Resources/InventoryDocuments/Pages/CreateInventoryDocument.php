<?php

namespace App\Filament\Resources\InventoryDocuments\Pages;

use App\Services\InventoryDocumentService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

abstract class CreateInventoryDocument extends CreateRecord
{
    protected static bool $canCreateAnother = false;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        $document = app(InventoryDocumentService::class)->create(
            static::getResource()::getDirection(),
            $data,
            auth()->user(),
        );
        $model = static::getResource()::getModel();

        return $model::query()->findOrFail($document->id);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }
}
