<?php

namespace App\Filament\Resources\InventoryDocuments;

use App\Filament\Resources\InventoryDocuments\RelationManagers\RevisionsRelationManager;
use App\Filament\Resources\InventoryDocuments\RelationManagers\TransactionsRelationManager;
use App\Models\InventoryDocument;
use App\Models\Part;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

abstract class InventoryDocumentResource extends Resource implements HasShieldPermissions
{
    protected static string $direction;

    protected static ?string $recordTitleAttribute = 'document_number';

    protected static string|\UnitEnum|null $navigationGroup = 'Warehouse Admin';

    public static function getDirection(): string
    {
        return static::$direction;
    }

    public static function getPermissionPrefixes(): array
    {
        return ['view_any', 'view', 'create', 'update'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Document')
                ->schema([
                    TextInput::make('document_number')
                        ->label('Document No.')
                        ->required()
                        ->maxLength(100)
                        ->unique(table: InventoryDocument::class, column: 'document_number', ignoreRecord: true),
                    DatePicker::make('document_date')
                        ->label('Document Date')
                        ->default(now())
                        ->required(),
                    Select::make('warehouse_id')
                        ->label('Warehouse')
                        ->options(fn (): array => Warehouse::query()->orderBy('code')->pluck('code', 'id')->all())
                        ->searchable()
                        ->live()
                        ->required(),
                    Textarea::make('memo')->rows(2)->columnSpanFull(),
                ])
                ->columns(3),
            Section::make('Items')
                ->schema([
                    Repeater::make('items')
                        ->hiddenLabel()
                        ->schema([
                            Select::make('part_id')
                                ->label('Part')
                                ->searchable()
                                ->getSearchResultsUsing(fn (string $search): array => self::partOptions($search))
                                ->getOptionLabelUsing(fn (mixed $value): ?string => self::partLabel($value))
                                ->required(),
                            Select::make('warehouse_location_id')
                                ->label('Location')
                                ->options(fn (Get $get): array => self::locationOptions($get('../../warehouse_id')))
                                ->searchable()
                                ->disabled(fn (Get $get): bool => blank($get('../../warehouse_id'))),
                            TextInput::make('quantity')
                                ->numeric()
                                ->minValue(0.000001)
                                ->required(),
                        ])
                        ->columns(3)
                        ->defaultItems(1)
                        ->minItems(1)
                        ->reorderable(false)
                        ->addActionLabel('Add item')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Document')
                ->schema([
                    TextEntry::make('document_number')->label('Document No.'),
                    TextEntry::make('document_date')->date(),
                    TextEntry::make('warehouse.code')->label('Warehouse'),
                    TextEntry::make('revision'),
                    TextEntry::make('posted_at')->dateTime(),
                    TextEntry::make('creator.email')->label('Created By')->placeholder('-'),
                    TextEntry::make('updater.email')->label('Updated By')->placeholder('-'),
                    TextEntry::make('memo')->placeholder('-')->columnSpanFull(),
                ])
                ->columns(4),
            Section::make('Items')
                ->schema([
                    RepeatableEntry::make('items')
                        ->hiddenLabel()
                        ->schema([
                            TextEntry::make('part.part_number')->label('Part No.'),
                            TextEntry::make('part.name')->label('Part Name'),
                            TextEntry::make('warehouseLocation.code')->label('Location')->placeholder('-'),
                            TextEntry::make('quantity')->numeric(decimalPlaces: 6),
                        ])
                        ->columns(4),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('document_number')->label('Document No.')->searchable()->sortable(),
                TextColumn::make('document_date')->date()->sortable(),
                TextColumn::make('warehouse.code')->label('Warehouse')->searchable()->sortable(),
                TextColumn::make('items_count')->counts('items')->label('Items'),
                TextColumn::make('revision')->sortable(),
                TextColumn::make('creator.email')->label('Created By')->toggleable(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('warehouse_id')
                    ->label('Warehouse')
                    ->options(fn (): array => Warehouse::query()->orderBy('code')->pluck('code', 'id')->all())
                    ->searchable(),
                SelectFilter::make('part')
                    ->label('Part')
                    ->options(fn (): array => self::partOptions())
                    ->searchable()
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] ?? null,
                            fn (Builder $query, int|string $partId): Builder => $query->whereHas(
                                'items',
                                fn (Builder $query): Builder => $query->where('part_id', $partId),
                            ),
                        );
                    }),
                Filter::make('document_date')
                    ->schema([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('document_date', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('document_date', '<=', $date));
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->defaultSort('document_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RevisionsRelationManager::class,
            TransactionsRelationManager::class,
        ];
    }

    /** @return array<int, string> */
    protected static function partOptions(?string $search = null): array
    {
        return Part::query()
            ->when(filled($search), fn (Builder $query): Builder => $query->search($search))
            ->orderBy('part_number')
            ->limit($search === null ? 100 : 50)
            ->get()
            ->mapWithKeys(fn (Part $part): array => [
                $part->id => "{$part->part_number} - {$part->name}",
            ])
            ->all();
    }

    protected static function partLabel(mixed $value): ?string
    {
        $part = Part::query()->find($value);

        return $part ? "{$part->part_number} - {$part->name}" : null;
    }

    /** @return array<int, string> */
    protected static function locationOptions(mixed $warehouseId): array
    {
        if (blank($warehouseId)) {
            return [];
        }

        return WarehouseLocation::query()
            ->where('warehouse_id', $warehouseId)
            ->orderBy('code')
            ->pluck('code', 'id')
            ->all();
    }
}
