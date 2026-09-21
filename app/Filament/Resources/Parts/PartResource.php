<?php

namespace App\Filament\Resources\Parts;

use App\Filament\Resources\Parts\Pages\CreatePart;
use App\Filament\Resources\Parts\Pages\EditPart;
use App\Filament\Resources\Parts\Pages\ListParts;
use App\Models\Part;
use App\Models\Supplier;
use App\Models\Warehouse;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PartResource extends Resource
{
    protected static ?string $model = Part::class;

    protected static ?string $recordTitleAttribute = 'part_number';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static string|\UnitEnum|null $navigationGroup = 'Warehouse Admin';

    protected static ?string $modelLabel = 'Part';

    protected static ?string $pluralModelLabel = 'Parts';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identity')
                ->schema([
                    TextInput::make('part_number')->required()->maxLength(255)->unique(ignoreRecord: true),
                    TextInput::make('name')->required()->maxLength(255),
                    TextInput::make('barcode')->maxLength(255),
                    TextInput::make('stock_unit')->maxLength(50),
                    TextInput::make('product_category')->maxLength(100),
                    TextInput::make('accounting_category')->maxLength(100),
                ])
                ->columns(3),
            Section::make('Warehouse')
                ->schema([
                    Select::make('primary_warehouse_id')
                        ->relationship('primaryWarehouse', 'code')
                        ->searchable()
                        ->preload(),
                    Select::make('primary_supplier_id')
                        ->relationship('primarySupplier', 'code')
                        ->searchable()
                        ->preload(),
                    TextInput::make('source_code')->maxLength(20),
                    TextInput::make('cycle_count_code')->maxLength(100),
                    Toggle::make('is_stock_calculated'),
                    Toggle::make('is_disabled'),
                    Toggle::make('is_replenished_on_demand'),
                ])
                ->columns(3),
            Section::make('Replenishment and Prices')
                ->schema([
                    TextInput::make('lead_days')->numeric()->minValue(0),
                    TextInput::make('safety_stock')->numeric(),
                    TextInput::make('minimum_replenishment_quantity')->numeric(),
                    TextInput::make('replenishment_multiple')->numeric(),
                    TextInput::make('standard_purchase_price')->numeric(),
                    TextInput::make('recent_purchase_price')->numeric(),
                    TextInput::make('retail_price')->numeric(),
                    TextInput::make('price_one')->numeric(),
                    TextInput::make('price_two')->numeric(),
                    TextInput::make('price_three')->numeric(),
                    TextInput::make('price_four')->numeric(),
                    TextInput::make('import_tariff_rate')->numeric(),
                    TextInput::make('unit_net_weight')->numeric(),
                ])
                ->columns(4),
            Section::make('Descriptions')
                ->schema([
                    TextInput::make('low_level_code')->maxLength(100),
                    TextInput::make('english_name')->maxLength(255),
                    Textarea::make('description')->rows(3),
                    Textarea::make('english_description')->rows(3),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('part_number')->label('Part No.')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->limit(40),
                TextColumn::make('barcode')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('stock_unit')->label('Unit'),
                TextColumn::make('product_category')->label('Category')->searchable(),
                TextColumn::make('accounting_category')->label('Accounting')->searchable()->toggleable(),
                TextColumn::make('primarySupplier.code')->label('Supplier')->searchable(),
                TextColumn::make('primaryWarehouse.code')->label('Warehouse')->searchable(),
                IconColumn::make('is_stock_calculated')->label('Stock')->boolean(),
                IconColumn::make('is_disabled')->label('Disabled')->boolean(),
                TextColumn::make('recent_purchase_price')->label('Recent Price')->numeric()->toggleable(),
                TextColumn::make('safety_stock')->numeric()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('product_category')->options(fn (): array => Part::query()->whereNotNull('product_category')->distinct()->orderBy('product_category')->pluck('product_category', 'product_category')->all()),
                SelectFilter::make('accounting_category')->options(fn (): array => Part::query()->whereNotNull('accounting_category')->distinct()->orderBy('accounting_category')->pluck('accounting_category', 'accounting_category')->all()),
                SelectFilter::make('primary_supplier_id')->label('Supplier')->options(fn (): array => Supplier::query()->orderBy('code')->pluck('code', 'id')->all()),
                SelectFilter::make('primary_warehouse_id')->label('Warehouse')->options(fn (): array => Warehouse::query()->orderBy('code')->pluck('code', 'id')->all()),
                TernaryFilter::make('is_disabled')->label('Disabled'),
                TernaryFilter::make('is_stock_calculated')->label('Stock calculated'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('part_number');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListParts::route('/'),
            'create' => CreatePart::route('/create'),
            'edit' => EditPart::route('/{record}/edit'),
        ];
    }
}
