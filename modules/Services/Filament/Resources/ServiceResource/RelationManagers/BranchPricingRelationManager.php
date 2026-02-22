<?php

namespace Modules\Services\Filament\Resources\ServiceResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use App\Filament\Resources\RelationManagers\BaseRelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Core\Models\Branch;

class BranchPricingRelationManager extends BaseRelationManager
{
    protected static string $relationship = 'branchPricing';

    protected static ?string $title = 'Branch Pricing';

    protected static ?string $recordTitleAttribute = 'branch_id';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('branch_id')
                    ->label(__('services::services.pricing.branch'))
                    ->options(Branch::query()->active()->pluck('name', 'id'))
                    ->required()
                    ->searchable()
                    ->disabledOn('edit'),

                Forms\Components\TextInput::make('price_minor')
                    ->label(__('services::services.pricing.price'))
                    ->numeric()
                    ->required()
                    ->suffix('piasters')
                    ->helperText('Enter price in piasters (100 piasters = 1 EGP)'),

                Forms\Components\Toggle::make('is_active')
                    ->label(__('services::services.pricing.is_active'))
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('branch.name')
                    ->label(__('services::services.pricing.branch'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('formatted_price')
                    ->label(__('services::services.pricing.price'))
                    ->sortable(['price_minor']),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('services::services.pricing.is_active'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('services::services.pricing.is_active')),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
