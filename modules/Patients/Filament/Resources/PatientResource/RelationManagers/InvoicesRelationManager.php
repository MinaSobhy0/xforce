<?php

namespace Modules\Patients\Filament\Resources\PatientResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

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
                    ->label(__('billing::billing.invoice'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('issued_at')
                    ->label(__('billing::billing.pdf.date'))
                    ->date()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label(__('billing::billing.statuses.draft'))
                    ->colors([
                        'gray' => 'draft',
                        'info' => 'issued',
                        'warning' => 'partially_paid',
                        'success' => 'paid',
                        'danger' => 'overdue',
                    ]),

                Tables\Columns\TextColumn::make('total_minor')
                    ->label(__('billing::billing.pdf.total'))
                    ->money(current_currency(), divideBy: 100)
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('paid_minor')
                    ->label(__('billing::billing.pdf.paid'))
                    ->money(current_currency(), divideBy: 100)
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('remaining_minor')
                    ->label(__('billing::billing.pdf.balance_due'))
                    ->money(current_currency(), divideBy: 100)
                    ->alignEnd()
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => __('billing::billing.statuses.draft'),
                        'issued' => __('billing::billing.statuses.issued'),
                        'paid' => __('billing::billing.statuses.paid'),
                        'overdue' => __('billing::billing.statuses.overdue'),
                    ]),
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn ($record) => route('filament.tenant.resources.invoices.view', $record)),
            ])
            ->bulkActions([])
            ->defaultSort('issued_at', 'desc');
    }

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('billing::billing.invoices');
    }
}
