<?php

namespace App\Filament\SuperAdmin\Resources\TenantResource\Pages;

use App\Filament\Resources\Pages\BaseEditRecord;
use App\Filament\SuperAdmin\Resources\TenantResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\Tenant;

class EditTenant extends BaseEditRecord
{
    protected static string $resource = TenantResource::class;

    /**
     * Same rationale as CreateTenant::handleRecordCreation — Tenant::$guarded
     * blocks subscription_plan_id and friends from mass-assignment so the
     * default Filament update silently drops them. Wrap the update in
     * Tenant::unguarded so the form save persists every field the
     * SuperAdmin actually edited, including the plan. The Tenant model's
     * updating hook then syncs tenant.features from the new plan unless
     * the form also touched features explicitly (Manage Modules flow).
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        Tenant::unguarded(function () use ($record, $data) {
            $record->fill($data);
            $record->save();
        });

        return $record;
    }

    protected function getEditHeaderActions(): array
    {
        return [
            Actions\Action::make('loginAs')
                ->label('Login to Admin Panel')
                ->icon('heroicon-o-arrow-right-on-rectangle')
                ->color('info')
                ->button()
                ->visible(fn () => $this->schemaExists())
                ->form([
                    Forms\Components\Select::make('user_id')
                        ->label('Select User')
                        ->options(function () {
                            try {
                                $schemaName = $this->record->database_name;
                                DB::statement("SET search_path TO \"{$schemaName}\"");

                                $users = DB::table('users')
                                    ->select('id', 'first_name', 'last_name', 'email', 'status')
                                    ->orderBy('first_name')
                                    ->get();

                                DB::statement('SET search_path TO public');

                                return $users->mapWithKeys(function ($user) {
                                    $name = trim("{$user->first_name} {$user->last_name}");
                                    $status = $user->status !== 'active' ? " [{$user->status}]" : '';

                                    return [$user->id => "{$name} ({$user->email}){$status}"];
                                });
                            } catch (\Exception $e) {
                                DB::statement('SET search_path TO public');

                                return [];
                            }
                        })
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data) {
                    try {
                        $schemaName = $this->record->database_name;
                        DB::statement("SET search_path TO \"{$schemaName}\"");

                        $token = \Illuminate\Support\Str::random(64);
                        $expiresAt = now()->addMinutes(5);

                        DB::table('users')
                            ->where('id', $data['user_id'])
                            ->update([
                                'impersonation_token' => password_hash($token, PASSWORD_BCRYPT),
                                'impersonation_token_expires_at' => $expiresAt,
                            ]);

                        DB::statement('SET search_path TO public');

                        $url = "https://{$this->record->slug}.x-linic.com/admin/impersonate?token={$token}&user={$data['user_id']}";

                        $this->js("window.open('{$url}', '_blank')");
                    } catch (\Exception $e) {
                        DB::statement('SET search_path TO public');

                        Notification::make()
                            ->title('Failed to generate login link')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function schemaExists(): bool
    {
        if (! $this->record || ! $this->record->database_name) {
            return false;
        }

        try {
            $result = DB::select(
                'SELECT schema_name FROM information_schema.schemata WHERE schema_name = ?',
                [$this->record->database_name]
            );

            return count($result) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
}
