<?php

namespace App\Filament\Resources\ImportBatches\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class RowsRelationManager extends RelationManager
{
    protected static string $relationship = 'rows';

    protected static ?string $title = 'Rows';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('row_number')->sortable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('part.part_number')->label('Part No.')->placeholder('-')->searchable(),
                TextColumn::make('error_message')->limit(80)->wrap()->placeholder('-'),
                TextColumn::make('raw_payload')
                    ->label('Raw Payload')
                    ->formatStateUsing(fn (array $state): string => Str::limit(json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '', 160))
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('row_number');
    }
}
