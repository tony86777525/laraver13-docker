<?php

namespace App\Filament\Resources\InboundDocuments;

use App\Filament\Resources\InboundDocuments\Pages\CreateInboundDocument;
use App\Filament\Resources\InboundDocuments\Pages\EditInboundDocument;
use App\Filament\Resources\InboundDocuments\Pages\ListInboundDocuments;
use App\Filament\Resources\InboundDocuments\Pages\ViewInboundDocument;
use App\Filament\Resources\InventoryDocuments\InventoryDocumentResource;
use App\Models\InboundDocument;
use App\Models\InventoryDocument;

class InboundDocumentResource extends InventoryDocumentResource
{
    protected static ?string $model = InboundDocument::class;

    protected static string $direction = InventoryDocument::DIRECTION_INBOUND;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static ?string $modelLabel = 'Inbound Document';

    protected static ?string $pluralModelLabel = 'Inbound Documents';

    public static function getPages(): array
    {
        return [
            'index' => ListInboundDocuments::route('/'),
            'create' => CreateInboundDocument::route('/create'),
            'view' => ViewInboundDocument::route('/{record}'),
            'edit' => EditInboundDocument::route('/{record}/edit'),
        ];
    }
}
