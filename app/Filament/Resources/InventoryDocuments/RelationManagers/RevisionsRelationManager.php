<?php

namespace App\Filament\Resources\InventoryDocuments\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RevisionsRelationManager extends RelationManager
{
    protected static string $relationship = 'revisions';

    protected static ?string $title = 'Revision History';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('revision')->sortable(),
                TextColumn::make('operator.email')->label('Operator')->placeholder('-'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('revision', 'desc');
    }
}
