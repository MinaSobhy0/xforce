<?php

namespace App\Filament\SuperAdmin\Resources\PlatformEmailListResource\RelationManagers;

use App\Models\PlatformEmailListMember;
use App\Services\Ai\AiImportOrganizer;
use App\Services\Ai\LlmProviderRegistry;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'members';

    protected static ?string $recordTitleAttribute = 'email';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('email')
                ->email()
                ->required()
                ->maxLength(255)
                ->dehydrateStateUsing(fn ($s) => mb_strtolower(trim((string) $s))),
            Forms\Components\TextInput::make('name_hint')->maxLength(255),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('email')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name_hint')->wrap(),
                Tables\Columns\TextColumn::make('source_type')->badge()->color('gray')->toggleable(),
                Tables\Columns\IconColumn::make('unsubscribed_at')
                    ->label('Subscribed')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->getStateUsing(fn ($record) => $record->unsubscribed_at === null),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('unsubscribed_at')
                    ->label('Subscribed')
                    ->nullable()
                    ->trueLabel('Subscribed only')
                    ->falseLabel('Unsubscribed only'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Add member'),

                Tables\Actions\Action::make('bulk_paste')
                    ->label('Bulk paste')
                    ->icon('heroicon-o-clipboard-document')
                    ->form([
                        Forms\Components\Textarea::make('addresses')
                            ->label('Email addresses')
                            ->rows(8)
                            ->required()
                            ->helperText('One per line, or comma-separated. Duplicates are ignored.'),
                    ])
                    ->action(function (array $data): void {
                        $emails = collect(preg_split('/[,\s]+/', (string) $data['addresses']))
                            ->map(fn ($e) => mb_strtolower(trim((string) $e)))
                            ->filter(fn ($e) => $e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL))
                            ->unique()
                            ->values();

                        $listId = $this->ownerRecord->id;
                        $added = 0;
                        foreach ($emails as $email) {
                            $created = PlatformEmailListMember::firstOrCreate(
                                ['list_id' => $listId, 'email' => $email],
                                ['source_type' => 'bulk_paste', 'subscribed_at' => now()],
                            );
                            if ($created->wasRecentlyCreated) {
                                $added++;
                            }
                        }

                        Notification::make()
                            ->title("Added {$added} new addresses")
                            ->success()->send();
                    }),

                Tables\Actions\Action::make('ai_import')
                    ->label('AI CSV import')
                    ->icon('heroicon-o-sparkles')
                    ->color('info')
                    ->visible(fn () => count(LlmProviderRegistry::available()) > 0)
                    ->form([
                        Forms\Components\FileUpload::make('file')
                            ->label('CSV or XLSX file')
                            ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                            ->maxSize(5120)
                            // Pin to the private 'local' disk in a scoped
                            // directory — Filament defaults to the 'public'
                            // disk otherwise, which both leaks the uploaded
                            // list to the world AND made my server-side
                            // resolver look in the wrong place.
                            ->disk('local')
                            ->directory('platform-email/ai-imports')
                            ->required(),
                        Forms\Components\Select::make('model')
                            ->label('AI model')
                            ->options(LlmProviderRegistry::available())
                            ->default(fn () => array_key_first(LlmProviderRegistry::available()))
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $this->handleAiImport($data);
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('unsubscribe')
                    ->icon('heroicon-o-no-symbol')
                    ->color('warning')
                    ->visible(fn ($record) => $record->unsubscribed_at === null)
                    ->requiresConfirmation()
                    ->action(fn ($record) => $record->update(['unsubscribed_at' => now()])),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    protected function handleAiImport(array $data): void
    {
        $path = $data['file'];
        $modelKey = $data['model'];

        // Resolve via the exact disk the FileUpload wrote to. The default
        // FileUpload disk is 'public' unless overridden — my code above
        // pins it to 'local' but keeping this defensive so a future disk
        // change (or a wizard step from a different resource) still works.
        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        if (! $disk->exists($path)) {
            // Fall back to 'public' for legacy uploads.
            $disk = \Illuminate\Support\Facades\Storage::disk('public');
            if (! $disk->exists($path)) {
                Notification::make()
                    ->title('Could not read file')
                    ->body("Uploaded file '{$path}' not found on local or public disk. Try uploading again.")
                    ->danger()->send();
                return;
            }
        }
        $absolute = $disk->path($path);

        try {
            [$headers, $rows] = $this->readSpreadsheet($absolute);
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Could not read file')
                ->body($e->getMessage())
                ->danger()->send();
            return;
        }

        if (empty($headers)) {
            Notification::make()->title('Empty file — no header row detected')->danger()->send();
            return;
        }

        $sample = array_slice($rows, 0, 50);

        try {
            $organizer = app(AiImportOrganizer::class);
            $plan = $organizer->organize($headers, $sample, $modelKey);
        } catch (\Throwable $e) {
            Notification::make()
                ->title('AI organizer failed')
                ->body($e->getMessage())
                ->danger()->send();
            return;
        }

        // Materialize the CONFIRMED rows using the AI plan.
        // (Simple auto-confirm for now — a review wizard step lands
        //  in a follow-up when we split confirm from materialize.)
        $added = 0;
        $skipped = 0;
        $listId = $this->ownerRecord->id;
        $sessionRef = 'ai_import_'.Str::random(8);

        foreach ($rows as $row) {
            $projected = $organizer->projectRow($row, $plan['mapping'], $plan['normalizations']);
            if ($projected === null) {
                $skipped++;
                continue;
            }
            $created = PlatformEmailListMember::firstOrCreate(
                ['list_id' => $listId, 'email' => $projected['email']],
                [
                    'name_hint' => $projected['name_hint'],
                    'source_type' => $sessionRef,
                    'subscribed_at' => now(),
                ],
            );
            if ($created->wasRecentlyCreated) {
                $added++;
            }
        }

        $costCents = (int) ($plan['cost_usd_cents'] ?? 0);
        Notification::make()
            ->title("Imported {$added} new members")
            ->body(
                "Skipped {$skipped} rows (invalid / missing email). "
                ."AI cost: {$costCents}¢ via {$plan['model_id']}."
            )
            ->success()->send();

        // Delete the uploaded file — we're done with it and don't want
        // list data sitting on disk after the import.
        try {
            $disk->delete($path);
        } catch (\Throwable $e) {
            // Best-effort cleanup; nothing to escalate.
        }
    }

    /**
     * @return array{0: list<string>, 1: list<array<string, string>>}
     */
    protected function readSpreadsheet(string $path): array
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($ext === 'csv' || $ext === 'txt') {
            return $this->readCsv($path);
        }

        // XLSX / XLS
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        $headers = array_map(fn ($h) => (string) $h, array_shift($rows) ?? []);
        $out = [];
        foreach ($rows as $r) {
            $row = [];
            foreach ($headers as $i => $h) {
                if ($h === '') {
                    continue;
                }
                $row[$h] = (string) ($r[$i] ?? '');
            }
            if (array_filter($row, fn ($v) => $v !== '')) {
                $out[] = $row;
            }
        }
        return [array_values(array_filter($headers, fn ($h) => $h !== '')), $out];
    }

    protected function readCsv(string $path): array
    {
        $fp = fopen($path, 'r');
        if ($fp === false) {
            throw new \RuntimeException('Could not open file');
        }
        try {
            $headers = fgetcsv($fp);
            if ($headers === false) {
                return [[], []];
            }
            $headers = array_map(fn ($h) => trim((string) $h), $headers);
            $rows = [];
            while (($line = fgetcsv($fp)) !== false) {
                $row = [];
                foreach ($headers as $i => $h) {
                    if ($h === '') {
                        continue;
                    }
                    $row[$h] = (string) ($line[$i] ?? '');
                }
                if (array_filter($row, fn ($v) => $v !== '')) {
                    $rows[] = $row;
                }
            }
            return [array_values(array_filter($headers, fn ($h) => $h !== '')), $rows];
        } finally {
            fclose($fp);
        }
    }
}
