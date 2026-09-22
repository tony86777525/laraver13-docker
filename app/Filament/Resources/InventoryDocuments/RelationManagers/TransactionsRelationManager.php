<?php

namespace App\Filament\Resources\InventoryDocuments\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('document_revision')->label('Revision')->sortable(),
                TextColumn::make('part.part_number')->label('Part No.'),
                TextColumn::make('warehouse.code')->label('Warehouse'),
                TextColumn::make('warehouseLocation.code')->label('Location')->placeholder('-'),
                TextColumn::make('before_quantity')->numeric(decimalPlaces: 6),
                TextColumn::make('quantity_delta')->numeric(decimalPlaces: 6),
                TextColumn::make('after_quantity')->numeric(decimalPlaces: 6),
                TextColumn::make('operator.email')->label('Operator')->placeholder('-'),
                TextColumn::make('occurred_at')->dateTime()->sortable(),
            ])
            ->defaultSort('occurred_at', 'desc');
    }
}
