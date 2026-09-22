<?php

namespace App\Filament\Resources\InventoryTransactions;

use App\Filament\Resources\InventoryTransactions\Pages\ListInventoryTransactions;
use App\Filament\Resources\InventoryTransactions\Pages\ViewInventoryTransaction;
use App\Models\InventoryTransaction;
use App\Models\Part;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InventoryTransactionResource extends Resource
{
    protected static ?string $model = InventoryTransaction::class;

    protected static ?string $recordTitleAttribute = 'id';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|\UnitEnum|null $navigationGroup = 'Warehouse Admin';

    protected static ?string $modelLabel = 'Inventory Transaction';

    protected static ?string $pluralModelLabel = 'Inventory Transactions';

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Transaction')
                ->schema([
                    TextEntry::make('transaction_type')->badge(),
                    TextEntry::make('occurred_at')->dateTime(),
                    TextEntry::make('part.part_number')->label('Part No.'),
                    TextEntry::make('part.name')->label('Part Name'),
                    TextEntry::make('warehouse.code')->label('Warehouse'),
                    TextEntry::make('warehouseLocation.code')->label('Location')->placeholder('-'),
                    TextEntry::make('before_quantity')->numeric(decimalPlaces: 6),
                    TextEntry::make('quantity_delta')->numeric(decimalPlaces: 6),
                    TextEntry::make('after_quantity')->numeric(decimalPlaces: 6),
                    TextEntry::make('reference_type')->placeholder('-'),
                    TextEntry::make('reference_number')->placeholder('-'),
                    TextEntry::make('document_revision')->label('Document Revision')->placeholder('-'),
                    TextEntry::make('operator.email')->label('Operator')->placeholder('-'),
                    TextEntry::make('memo')->columnSpanFull()->placeholder('-'),
                ])
                ->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('occurred_at')->dateTime()->sortable(),
                TextColumn::make('transaction_type')->badge()->searchable(),
                TextColumn::make('part.part_number')->label('Part No.')->searchable(),
                TextColumn::make('part.name')->label('Part Name')->limit(36)->toggleable(),
                TextColumn::make('warehouse.code')->label('Warehouse')->searchable(),
                TextColumn::make('warehouseLocation.code')->label('Location')->placeholder('-')->searchable(),
                TextColumn::make('before_quantity')->numeric(decimalPlaces: 6)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('quantity_delta')->numeric(decimalPlaces: 6)->sortable(),
                TextColumn::make('after_quantity')->numeric(decimalPlaces: 6),
                TextColumn::make('reference_type')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reference_number')->searchable()->toggleable(),
                TextColumn::make('document_revision')->label('Revision')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('operator.email')->label('Operator')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('part_id')->label('Part')->options(fn (): array => self::partOptions())->searchable(),
                SelectFilter::make('warehouse_id')->label('Warehouse')->options(fn (): array => Warehouse::query()->orderBy('code')->pluck('code', 'id')->all())->searchable(),
                SelectFilter::make('warehouse_location_id')->label('Location')->options(fn (): array => self::locationOptions())->searchable(),
                SelectFilter::make('transaction_type')->label('Type')->options(InventoryTransaction::typeOptions()),
                Filter::make('occurred_at')
                    ->schema([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('occurred_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('occurred_at', '<=', $date));
                    }),
                Filter::make('reference')
                    ->schema([
                        TextInput::make('value')->label('Reference'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $reference = trim((string) ($data['value'] ?? ''));

                        if ($reference === '') {
                            return $query;
                        }

                        return $query->where(function (Builder $query) use ($reference): void {
                            $query
                                ->where('reference_type', 'like', "%{$reference}%")
                                ->orWhere('reference_number', 'like', "%{$reference}%");
                        });
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('occurred_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventoryTransactions::route('/'),
            'view' => ViewInventoryTransaction::route('/{record}'),
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function partOptions(): array
    {
        return Part::query()
            ->orderBy('part_number')
            ->get()
            ->mapWithKeys(fn (Part $part): array => [
                $part->id => "{$part->part_number} - {$part->name}",
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function locationOptions(): array
    {
        return WarehouseLocation::query()
            ->with('warehouse')
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (WarehouseLocation $location): array => [
                $location->id => trim(($location->warehouse?->code ? "{$location->warehouse->code} / " : '').$location->code),
            ])
            ->all();
    }
}
