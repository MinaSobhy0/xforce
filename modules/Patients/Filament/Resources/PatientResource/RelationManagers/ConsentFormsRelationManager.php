<?php

namespace Modules\Patients\Filament\Resources\PatientResource\RelationManagers;

use Modules\Patients\Models\PatientConsentForm;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ConsentFormsRelationManager extends RelationManager
{
    protected static string $relationship = 'consentForms';

    protected static ?string $title = 'Consent Forms';

    protected static ?string $icon = 'heroicon-o-document-check';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('consent_template_id')
                    ->label('Consent Template')
                    ->relationship('consentTemplate', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),

                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\DateTimePicker::make('signed_at')
                            ->label(__('patients::patients.consent.signed_at'))
                            ->required()
                            ->default(now()),

                        Forms\Components\DateTimePicker::make('valid_until')
                            ->label(__('patients::patients.consent.valid_until')),
                    ]),

                Forms\Components\Select::make('signature_type')
                    ->label('Signature Type')
                    ->options(PatientConsentForm::SIGNATURE_TYPES)
                    ->default('drawn')
                    ->required(),

                Forms\Components\Textarea::make('signature_data')
                    ->label(__('patients::patients.consent.signature'))
                    ->helperText('Paste signature data or draw signature')
                    ->rows(3),

                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('witness_name')
                            ->label(__('patients::patients.consent.witness'))
                            ->maxLength(200),

                        Forms\Components\Toggle::make('signed_by_patient')
                            ->label('Signed by Patient')
                            ->default(true),
                    ]),

                Forms\Components\Textarea::make('notes')
                    ->label('Notes')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('signed_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('consentTemplate.name')
                    ->label('Template')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('signed_at')
                    ->label(__('patients::patients.consent.signed_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('valid_until')
                    ->label(__('patients::patients.consent.valid_until'))
                    ->dateTime('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($record) => $record->status_color),

                Tables\Columns\IconColumn::make('signed_by_patient')
                    ->label('Patient Signed')
                    ->boolean(),

                Tables\Columns\TextColumn::make('staff.name')
                    ->label('Collected By'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'valid' => 'Valid',
                        'expired' => 'Expired',
                        'revoked' => 'Revoked',
                    ])
                    ->query(function ($query, array $data) {
                        return match ($data['value']) {
                            'valid' => $query->valid(),
                            'expired' => $query->whereNotNull('valid_until')
                                ->where('valid_until', '<=', now())
                                ->whereNull('revoked_at'),
                            'revoked' => $query->whereNotNull('revoked_at'),
                            default => $query,
                        };
                    }),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['staff_id'] = auth()->id();
                        $data['ip_address'] = request()->ip();
                        $data['user_agent'] = request()->userAgent();
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('download')
                    ->label(__('patients::patients.consent.download'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->visible(fn ($record) => $record->pdf_path)
                    ->url(fn ($record) => $record->pdf_path),
                Tables\Actions\Action::make('revoke')
                    ->label('Revoke')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => $record->isValid())
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Revocation Reason')
                            ->required(),
                    ])
                    ->action(fn ($record, array $data) => $record->revoke($data['reason'])),
            ])
            ->bulkActions([]);
    }
}
