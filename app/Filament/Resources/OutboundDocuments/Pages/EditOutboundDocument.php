<?php

namespace App\Filament\Resources\OutboundDocuments\Pages;

use App\Filament\Resources\InventoryDocuments\Pages\EditInventoryDocument;
use App\Filament\Resources\OutboundDocuments\OutboundDocumentResource;

class EditOutboundDocument extends EditInventoryDocument
{
    protected static string $resource = OutboundDocumentResource::class;
}
