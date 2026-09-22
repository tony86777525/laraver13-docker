<?php

namespace App\Filament\Resources\OutboundDocuments\Pages;

use App\Filament\Resources\InventoryDocuments\Pages\CreateInventoryDocument;
use App\Filament\Resources\OutboundDocuments\OutboundDocumentResource;

class CreateOutboundDocument extends CreateInventoryDocument
{
    protected static string $resource = OutboundDocumentResource::class;
}
