<?php

namespace Modules\Loyalty\Filament\Resources\ReferralProgramResource\RelationManagers;

use Modules\Loyalty\Models\Referral;
use Filament\Forms;
use Filament\Forms\Form;
use App\Filament\Resources\RelationManagers\BaseRelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ReferralsRelationManager extends BaseRelationManager
{
    protected static string $relationship = 'referrals';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('referrer_patient_id')
                    ->label(__('loyalty::loyalty.fields.referrer'))
                    ->relationship('referrerPatient', 'full_name')
                    ->searchable()
                    ->required(),

                Forms\Components\Select::make('referred_patient_id')
                    ->label(__('loyalty::loyalty.fields.referred'))
                    ->relationship('referredPatient', 'full_name')
                    ->searchable(),

                Forms\Components\Select::make('status')
                    ->label(__('loyalty::loyalty.fields.status'))
                    ->options(Referral::getStatuses())
                    ->required(),

                Forms\Components\Textarea::make('notes')
                    ->label(__('loyalty::loyalty.fields.description'))
                    ->rows(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('code')
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('loyalty::loyalty.fields.code'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('referrerPatient.full_name')
                    ->label(__('loyalty::loyalty.fields.referrer'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('referredPatient.full_name')
                    ->label(__('loyalty::loyalty.fields.referred'))
                    ->searchable()
                    ->sortable()
                    ->placeholder('Pending'),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('loyalty::loyalty.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => Referral::getStatuses()[$state] ?? $state)
                    ->color(fn ($state) => Referral::getStatusColors()[$state] ?? 'gray'),

                Tables\Columns\TextColumn::make('referrer_points_awarded')
                    ->label(__('loyalty::loyalty.fields.referrer_points'))
                    ->numeric()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('referred_points_awarded')
                    ->label(__('loyalty::loyalty.fields.referred_points'))
                    ->numeric()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('rewarded_at')
                    ->label('Rewarded At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('core::core.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('loyalty::loyalty.fields.status'))
                    ->options(Referral::getStatuses()),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
