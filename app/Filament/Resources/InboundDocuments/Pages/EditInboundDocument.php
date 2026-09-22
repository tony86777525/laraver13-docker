<?php

namespace App\Filament\Resources\InboundDocuments\Pages;

use App\Filament\Resources\InboundDocuments\InboundDocumentResource;
use App\Filament\Resources\InventoryDocuments\Pages\EditInventoryDocument;

class EditInboundDocument extends EditInventoryDocument
{
    protected static string $resource = InboundDocumentResource::class;
}
