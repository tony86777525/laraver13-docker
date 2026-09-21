<?php

namespace App\Filament\Resources\ImportBatches;

use App\Filament\Resources\ImportBatches\Pages\ListImportBatches;
use App\Filament\Resources\ImportBatches\Pages\ViewImportBatch;
use App\Filament\Resources\ImportBatches\RelationManagers\RowsRelationManager;
use App\Models\ImportBatch;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ImportBatchResource extends Resource
{
    protected static ?string $model = ImportBatch::class;

    protected static ?string $recordTitleAttribute = 'source_filename';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-arrow-up';

    protected static string|\UnitEnum|null $navigationGroup = 'Warehouse Admin';

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Batch')
                ->schema([
                    TextEntry::make('import_type'),
                    TextEntry::make('source_filename')->placeholder('-'),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('total_rows')->numeric(),
                    TextEntry::make('successful_rows')->numeric(),
                    TextEntry::make('failed_rows')->numeric(),
                    TextEntry::make('started_at')->dateTime()->placeholder('-'),
                    TextEntry::make('finished_at')->dateTime()->placeholder('-'),
                    TextEntry::make('error_message')->columnSpanFull()->placeholder('-'),
                ])
                ->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('source_filename')->searchable()->placeholder('-'),
                TextColumn::make('import_type')->badge(),
                TextColumn::make('status')->badge()->searchable(),
                TextColumn::make('total_rows')->numeric()->sortable(),
                TextColumn::make('successful_rows')->numeric()->sortable(),
                TextColumn::make('failed_rows')->numeric()->sortable(),
                TextColumn::make('creator.email')->label('Created By')->toggleable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    ImportBatch::STATUS_PENDING => 'Pending',
                    ImportBatch::STATUS_PROCESSING => 'Processing',
                    ImportBatch::STATUS_COMPLETED => 'Completed',
                    ImportBatch::STATUS_COMPLETED_WITH_ERRORS => 'Completed with errors',
                    ImportBatch::STATUS_FAILED => 'Failed',
                ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RowsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListImportBatches::route('/'),
            'view' => ViewImportBatch::route('/{record}'),
        ];
    }
}
