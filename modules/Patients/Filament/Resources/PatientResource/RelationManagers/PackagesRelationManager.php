<?php

namespace Modules\Patients\Filament\Resources\PatientResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use App\Filament\Resources\RelationManagers\BaseRelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PackagesRelationManager extends BaseRelationManager
{
    protected static string $relationship = 'packageSubscriptions';

    protected static ?string $recordTitleAttribute = 'code';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('code')
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('packages::packages.fields.code'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('package.name')
                    ->label(__('packages::packages.package'))
                    ->formatStateUsing(fn ($state) => is_array($state) ? ($state[app()->getLocale()] ?? $state['en'] ?? '') : $state),

                Tables\Columns\TextColumn::make('purchased_at')
                    ->label(__('packages::packages.fields.purchased_at'))
                    ->date()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label(__('packages::packages.fields.status'))
                    ->colors([
                        'success' => 'active',
                        'warning' => 'frozen',
                        'gray' => 'completed',
                        'danger' => fn ($state) => in_array($state, ['expired', 'cancelled']),
                    ]),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label(__('packages::packages.fields.expires_at'))
                    ->date()
                    ->color(fn ($state) => $state && $state < now() ? 'danger' : null),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => __('packages::packages.statuses.active'),
                        'completed' => __('packages::packages.statuses.completed'),
                        'expired' => __('packages::packages.statuses.expired'),
                    ]),
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn ($record) => route('filament.tenant.resources.package-subscriptions.view', $record)),
            ])
            ->bulkActions([])
            ->defaultSort('purchased_at', 'desc');
    }

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('packages::packages.packages');
    }
}
