<?php

namespace App\Filament\Resources\Inventories;

use App\Filament\Resources\Inventories\Pages\ListInventories;
use App\Models\Inventory;
use App\Models\Part;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InventoryResource extends Resource
{
    protected static ?string $model = Inventory::class;

    protected static ?string $recordTitleAttribute = 'id';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';

    protected static string|\UnitEnum|null $navigationGroup = 'Warehouse Admin';

    protected static ?string $modelLabel = 'Inventory';

    protected static ?string $pluralModelLabel = 'Inventories';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('part.part_number')->label('Part No.')->searchable()->sortable(),
                TextColumn::make('part.name')->label('Part Name')->searchable()->limit(40),
                TextColumn::make('part.stock_unit')->label('Unit'),
                TextColumn::make('warehouse.code')->label('Warehouse')->searchable()->sortable(),
                TextColumn::make('warehouseLocation.code')->label('Location')->placeholder('-')->searchable(),
                TextColumn::make('quantity')->numeric(decimalPlaces: 6)->sortable(),
                TextColumn::make('part.safety_stock')->label('Safety Stock')->numeric(decimalPlaces: 6)->toggleable(),
                TextColumn::make('part.recent_purchase_price')->label('Recent Price')->numeric(decimalPlaces: 6)->toggleable(),
                IconColumn::make('part.is_stock_calculated')->label('Stock')->boolean(),
                IconColumn::make('part.is_disabled')->label('Disabled')->boolean(),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('part_id')->label('Part')->options(fn (): array => self::partOptions())->searchable(),
                SelectFilter::make('warehouse_id')->label('Warehouse')->options(fn (): array => Warehouse::query()->orderBy('code')->pluck('code', 'id')->all())->searchable(),
                SelectFilter::make('warehouse_location_id')->label('Location')->options(fn (): array => self::locationOptions())->searchable(),
                TernaryFilter::make('is_disabled')
                    ->label('Disabled')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('part', fn (Builder $query): Builder => $query->where('is_disabled', true)),
                        false: fn (Builder $query): Builder => $query->whereHas('part', fn (Builder $query): Builder => $query->where('is_disabled', false)),
                    ),
                TernaryFilter::make('is_stock_calculated')
                    ->label('Stock calculated')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('part', fn (Builder $query): Builder => $query->where('is_stock_calculated', true)),
                        false: fn (Builder $query): Builder => $query->whereHas('part', fn (Builder $query): Builder => $query->where('is_stock_calculated', false)),
                    ),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventories::route('/'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function partOptions(): array
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
    public static function locationOptions(mixed $warehouseId = null): array
    {
        return WarehouseLocation::query()
            ->with('warehouse')
            ->when(
                filled($warehouseId),
                fn (Builder $query): Builder => $query->where('warehouse_id', $warehouseId),
            )
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (WarehouseLocation $location): array => [
                $location->id => trim(($location->warehouse?->code ? "{$location->warehouse->code} / " : '').$location->code),
            ])
            ->all();
    }
}
