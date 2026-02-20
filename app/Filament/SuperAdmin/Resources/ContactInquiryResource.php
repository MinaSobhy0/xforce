<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\ContactInquiryResource\Pages;
use App\Models\ContactInquiry;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Enums\FontWeight;

class ContactInquiryResource extends Resource
{
    protected static ?string $model = ContactInquiry::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox';

    protected static ?string $navigationLabel = 'Contact Inquiries';

    protected static ?string $navigationGroup = 'Leads';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Contact Information')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('clinic_name')
                        ->label('Clinic Name')
                        ->disabled(),

                    Forms\Components\TextInput::make('contact_name')
                        ->label('Contact Name')
                        ->disabled(),

                    Forms\Components\TextInput::make('email')
                        ->label('Email')
                        ->disabled(),

                    Forms\Components\TextInput::make('phone')
                        ->label('Phone')
                        ->disabled(),

                    Forms\Components\TextInput::make('country')
                        ->label('Country')
                        ->disabled(),

                    Forms\Components\Select::make('status')
                        ->options([
                            'new' => 'New',
                            'contacted' => 'Contacted',
                            'converted' => 'Converted',
                            'closed' => 'Closed',
                        ])
                        ->required(),
                ]),

            Forms\Components\Section::make('Message')
                ->schema([
                    Forms\Components\Textarea::make('message')
                        ->label('Message')
                        ->disabled()
                        ->rows(4),
                ]),

            Forms\Components\Section::make('Internal Notes')
                ->schema([
                    Forms\Components\Textarea::make('notes')
                        ->label('Notes')
                        ->rows(4)
                        ->placeholder('Add internal notes about this inquiry...'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('clinic_name')
                    ->label('Clinic')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('contact_name')
                    ->label('Contact')
                    ->searchable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('country')
                    ->label('Country')
                    ->formatStateUsing(fn(?string $state): string => match ($state) {
                        'EG' => 'Egypt',
                        'SA' => 'Saudi Arabia',
                        'AE' => 'UAE',
                        'KW' => 'Kuwait',
                        'QA' => 'Qatar',
                        'BH' => 'Bahrain',
                        'OM' => 'Oman',
                        'JO' => 'Jordan',
                        'LB' => 'Lebanon',
                        default => $state ?? '-',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'new' => 'info',
                        'contacted' => 'warning',
                        'converted' => 'success',
                        'closed' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Received')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'new' => 'New',
                        'contacted' => 'Contacted',
                        'converted' => 'Converted',
                        'closed' => 'Closed',
                    ]),

                Tables\Filters\SelectFilter::make('country')
                    ->options([
                        'EG' => 'Egypt',
                        'SA' => 'Saudi Arabia',
                        'AE' => 'UAE',
                        'KW' => 'Kuwait',
                        'QA' => 'Qatar',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('markContacted')
                    ->label('Mark Contacted')
                    ->icon('heroicon-o-phone')
                    ->color('warning')
                    ->visible(fn(ContactInquiry $record) => $record->status === 'new')
                    ->action(fn(ContactInquiry $record) => $record->update(['status' => 'contacted'])),

                Tables\Actions\Action::make('markConverted')
                    ->label('Convert to Tenant')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn(ContactInquiry $record) => in_array($record->status, ['new', 'contacted']))
                    ->url(fn(ContactInquiry $record) => route('filament.super-admin.resources.tenants.create', [
                        'clinic_name' => $record->clinic_name,
                        'contact_name' => $record->contact_name,
                        'contact_email' => $record->email,
                        'contact_phone' => $record->phone,
                        'country' => $record->country,
                    ])),

                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('markContacted')
                    ->label('Mark as Contacted')
                    ->icon('heroicon-o-phone')
                    ->action(fn($records) => $records->each->update(['status' => 'contacted'])),

                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContactInquiries::route('/'),
            'edit' => Pages\EditContactInquiry::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::where('status', 'new')->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }
}
