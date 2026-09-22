<?php

namespace App\Filament\Resources\InboundDocuments\Pages;

use App\Filament\Resources\InboundDocuments\InboundDocumentResource;
use App\Filament\Resources\InventoryDocuments\Pages\CreateInventoryDocument;

class CreateInboundDocument extends CreateInventoryDocument
{
    protected static string $resource = InboundDocumentResource::class;
}
