<?php

namespace App\Filament\Resources\OutboundDocuments\Pages;

use App\Filament\Resources\InventoryDocuments\Pages\ListInventoryDocuments;
use App\Filament\Resources\OutboundDocuments\OutboundDocumentResource;

class ListOutboundDocuments extends ListInventoryDocuments
{
    protected static string $resource = OutboundDocumentResource::class;
}
