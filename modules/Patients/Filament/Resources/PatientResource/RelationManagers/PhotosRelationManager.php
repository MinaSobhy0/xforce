<?php

namespace Modules\Patients\Filament\Resources\PatientResource\RelationManagers;

use App\Traits\EnforcesStorageQuota;
use Modules\Patients\Models\PatientPhoto;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class PhotosRelationManager extends RelationManager
{
    use EnforcesStorageQuota;

    protected static string $relationship = 'photos';

    protected static ?string $title = 'Photos';

    protected static ?string $icon = 'heroicon-o-photo';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // SECURITY: Limit file size to remaining storage quota
                Forms\Components\SpatieMediaLibraryFileUpload::make('photo')
                    ->collection('photos')
                    ->image()
                    ->imageEditor()
                    ->required()
                    ->maxSize(function () {
                        // Get remaining storage in KB (maxSize expects KB)
                        $remainingMb = $this->getRemainingStorageMb();
                        // Cap individual file size at 50MB or remaining quota, whichever is smaller
                        $maxMb = min(50, $remainingMb);
                        return $maxMb * 1024; // Convert to KB
                    })
                    ->helperText(function () {
                        $remainingMb = $this->getRemainingStorageMb();
                        $usagePercent = $this->getStorageUsagePercentage();
                        if ($usagePercent >= 80) {
                            return __('patients::patients.photos.storage_warning', [
                                'remaining' => $remainingMb,
                            ]);
                        }
                        return null;
                    })
                    ->columnSpanFull(),

                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->label(__('patients::patients.photos.type'))
                            ->options(PatientPhoto::TYPES)
                            ->required()
                            ->default('consultation'),

                        Forms\Components\Select::make('body_area')
                            ->label(__('patients::patients.photos.body_area'))
                            ->options(PatientPhoto::BODY_AREAS)
                            ->searchable(),
                    ]),

                Forms\Components\DateTimePicker::make('taken_at')
                    ->label(__('patients::patients.photos.taken_at'))
                    ->default(now()),

                Forms\Components\Textarea::make('description')
                    ->label('Description')
                    ->rows(2)
                    ->columnSpanFull(),

                Forms\Components\Toggle::make('is_private')
                    ->label('Private (staff only)')
                    ->default(false),

                Forms\Components\TagsInput::make('tags')
                    ->label('Tags'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('taken_at', 'desc')
            ->columns([
                Tables\Columns\SpatieMediaLibraryImageColumn::make('photo')
                    ->collection('photos')
                    ->conversion('thumb')
                    ->width(80)
                    ->height(80),

                Tables\Columns\TextColumn::make('type')
                    ->label(__('patients::patients.photos.type'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'before' => 'info',
                        'after' => 'success',
                        'during' => 'warning',
                        'reaction' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('body_area')
                    ->label(__('patients::patients.photos.body_area'))
                    ->formatStateUsing(fn ($state) => PatientPhoto::BODY_AREAS[$state] ?? $state),

                Tables\Columns\TextColumn::make('taken_at')
                    ->label(__('patients::patients.photos.taken_at'))
                    ->dateTime('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('takenBy.name')
                    ->label(__('patients::patients.photos.taken_by')),

                Tables\Columns\IconColumn::make('is_private')
                    ->label('Private')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(PatientPhoto::TYPES),

                Tables\Filters\SelectFilter::make('body_area')
                    ->options(PatientPhoto::BODY_AREAS),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['taken_by'] = auth()->id();
                        $data['taken_at'] = $data['taken_at'] ?? now();
                        return $data;
                    })
                    // SECURITY: Enforce storage quota before upload
                    ->before(function (array $data) {
                        // Check if nearing quota and warn
                        $usagePercent = $this->getStorageUsagePercentage();
                        if ($usagePercent >= 90) {
                            $this->notifyStorageQuotaWarning($usagePercent, $this->getRemainingStorageMb());
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
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
