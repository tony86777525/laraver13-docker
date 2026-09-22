<?php

namespace App\Filament\Resources\OutboundDocuments\Pages;

use App\Filament\Resources\InventoryDocuments\Pages\ViewInventoryDocument;
use App\Filament\Resources\OutboundDocuments\OutboundDocumentResource;

class ViewOutboundDocument extends ViewInventoryDocument
{
    protected static string $resource = OutboundDocumentResource::class;
}
