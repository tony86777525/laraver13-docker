<?php

namespace App\Filament\Resources\OutboundDocuments;

use App\Filament\Resources\InventoryDocuments\InventoryDocumentResource;
use App\Filament\Resources\OutboundDocuments\Pages\CreateOutboundDocument;
use App\Filament\Resources\OutboundDocuments\Pages\EditOutboundDocument;
use App\Filament\Resources\OutboundDocuments\Pages\ListOutboundDocuments;
use App\Filament\Resources\OutboundDocuments\Pages\ViewOutboundDocument;
use App\Models\InventoryDocument;
use App\Models\OutboundDocument;

class OutboundDocumentResource extends InventoryDocumentResource
{
    protected static ?string $model = OutboundDocument::class;

    protected static string $direction = InventoryDocument::DIRECTION_OUTBOUND;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $modelLabel = 'Outbound Document';

    protected static ?string $pluralModelLabel = 'Outbound Documents';

    public static function getPages(): array
    {
        return [
            'index' => ListOutboundDocuments::route('/'),
            'create' => CreateOutboundDocument::route('/create'),
            'view' => ViewOutboundDocument::route('/{record}'),
            'edit' => EditOutboundDocument::route('/{record}/edit'),
        ];
    }
}
