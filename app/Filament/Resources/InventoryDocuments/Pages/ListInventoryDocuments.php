<?php

namespace App\Filament\Resources\InventoryDocuments\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

abstract class ListInventoryDocuments extends ListRecords
{
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
