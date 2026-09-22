<?php

namespace App\Filament\Resources\Inventories\Pages;

use App\Filament\Resources\Inventories\InventoryResource;
use App\Models\InventoryTransaction;
use App\Models\Warehouse;
use App\Services\InventoryAdjustmentService;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class ListInventories extends ListRecords
{
    protected static string $resource = InventoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('adjustInventory')
                ->label('Adjust Inventory')
                ->visible(fn (): bool => InventoryResource::canCreate())
                ->schema([
                    Grid::make(2)
                        ->schema([
                            Select::make('part_id')
                                ->label('Part')
                                ->options(fn (): array => InventoryResource::partOptions())
                                ->searchable()
                                ->required(),
                            Select::make('warehouse_id')
                                ->label('Warehouse')
                                ->options(fn (): array => Warehouse::query()->orderBy('code')->pluck('code', 'id')->all())
                                ->searchable()
                                ->live()
                                ->afterStateUpdated(fn (Set $set): mixed => $set('warehouse_location_id', null))
                                ->required(),
                            Select::make('warehouse_location_id')
                                ->label('Location')
                                ->options(fn (Get $get): array => InventoryResource::locationOptions($get('warehouse_id')))
                                ->searchable()
                                ->preload()
                                ->disabled(fn (Get $get): bool => blank($get('warehouse_id'))),
                            Select::make('transaction_type')
                                ->label('Transaction Type')
                                ->options([
                                    InventoryTransaction::TYPE_ADJUSTMENT => 'Adjustment',
                                    InventoryTransaction::TYPE_CYCLE_COUNT => 'Cycle count',
                                    InventoryTransaction::TYPE_IMPORT_CORRECTION => 'Import correction',
                                ])
                                ->default(InventoryTransaction::TYPE_ADJUSTMENT)
                                ->required(),
                            TextInput::make('quantity_delta')
                                ->label('Quantity Delta')
                                ->numeric()
                                ->required(),
                            TextInput::make('reference_type')->maxLength(100),
                            TextInput::make('reference_number')->maxLength(100),
                            DateTimePicker::make('occurred_at')->seconds(false),
                            Textarea::make('memo')->rows(3)->columnSpanFull(),
                        ]),
                ])
                ->action(function (array $data, InventoryAdjustmentService $service): void {
                    $service->adjust($data, auth()->user());

                    Notification::make()
                        ->title('Inventory adjusted')
                        ->success()
                        ->send();
                }),
        ];
    }
}
