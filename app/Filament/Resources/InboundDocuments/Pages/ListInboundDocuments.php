<?php

namespace App\Filament\Resources\InboundDocuments\Pages;

use App\Filament\Resources\InboundDocuments\InboundDocumentResource;
use App\Filament\Resources\InventoryDocuments\Pages\ListInventoryDocuments;

class ListInboundDocuments extends ListInventoryDocuments
{
    protected static string $resource = InboundDocumentResource::class;
}
