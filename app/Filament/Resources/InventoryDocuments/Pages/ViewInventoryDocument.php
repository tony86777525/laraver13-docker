<?php

namespace App\Filament\Resources\InventoryDocuments\Pages;

use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

abstract class ViewInventoryDocument extends ViewRecord
{
    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
