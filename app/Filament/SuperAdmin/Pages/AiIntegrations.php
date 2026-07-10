<?php

namespace App\Filament\SuperAdmin\Pages;

use App\Models\PlatformSetting;
use App\Services\Ai\LlmProviderRegistry;
use App\Services\Ai\LlmRequest;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * SuperAdmin page for managing LLM provider API keys + default model.
 * Values persist in platform_settings (encrypted for keys) so the
 * env-only fallback becomes optional. Includes a per-provider "Test"
 * button that fires a canned "Say hi" prompt and reports latency,
 * tokens, and estimated cost.
 */
class AiIntegrations extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $navigationLabel = 'AI Integrations';

    protected static ?string $title = 'AI Integrations';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 80;

    protected static string $view = 'filament.super-admin.pages.ai-integrations';

    public ?array $data = [];

    public function mount(): void
    {
        $providers = (array) config('llm.providers', []);
        $current = [];
        foreach (array_keys($providers) as $key) {
            // Wrapped in try/catch so a corrupted/undecryptable row from a
            // prior buggy save doesn't take out the whole page. The user
            // just re-enters the key and Save cleans it up.
            try {
                $current['api_key_'.$key] = PlatformSetting::get('llm.api_key.'.$key);
            } catch (\Throwable $e) {
                $current['api_key_'.$key] = null;
            }
        }
        $current['default'] = LlmProviderRegistry::defaultModelKey();

        $this->form->fill($current);
    }

    public function form(Form $form): Form
    {
        $providers = (array) config('llm.providers', []);

        $providerFields = [];
        foreach ($providers as $key => $cfg) {
            $envKey = static::envKeyForConfig($cfg);
            $envFallback = $envKey ? env($envKey) : null;

            $providerFields[] = Forms\Components\Section::make($cfg['label'] ?? $key)
                ->description(sprintf(
                    'Model %s · $%.2f/M in · $%.2f/M out',
                    $cfg['model'] ?? $key,
                    (float) ($cfg['input_cost_per_mtok'] ?? 0),
                    (float) ($cfg['output_cost_per_mtok'] ?? 0),
                ))
                ->collapsed(! filled(PlatformSetting::get('llm.api_key.'.$key)) && ! filled($envFallback))
                ->schema([
                    Forms\Components\TextInput::make('api_key_'.$key)
                        ->label('API key')
                        ->password()
                        ->revealable()
                        ->helperText($envFallback
                            ? "Currently sourced from .env ({$envKey}). Setting a value here overrides it."
                            : 'Not yet configured.'),
                ]);
        }

        return $form
            ->schema([
                Forms\Components\Section::make('Default')
                    ->description('Model used when a campaign or import wizard doesn\'t override it explicitly.')
                    ->schema([
                        Forms\Components\Select::make('default')
                            ->options(collect($providers)->mapWithKeys(fn ($cfg, $key) => [$key => $cfg['label'] ?? $key])->all())
                            ->required()
                            ->helperText('Only providers with a configured API key can actually run.'),
                    ]),

                ...$providerFields,
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ((array) config('llm.providers', []) as $key => $_cfg) {
            $settingKey = 'llm.api_key.'.$key;
            $value = $data['api_key_'.$key] ?? null;

            if (filled($value)) {
                // Persist via the model directly (instead of the static
                // ::set() helper) so setIsEncryptedAttribute() runs BEFORE
                // setValueAttribute() and the value actually lands
                // encrypted. The static helper writes the raw plaintext
                // and marks is_encrypted=true, which then explodes on read.
                $s = PlatformSetting::firstOrNew(['key' => $settingKey]);
                $s->group = 'llm';
                $s->type = 'string';
                $s->is_encrypted = true;
                $s->value = $value; // setValueAttribute encrypts because is_encrypted is true
                $s->save();
            } else {
                // Empty submission clears the DB value (falls back to .env).
                PlatformSetting::where('key', $settingKey)->delete();
            }
        }

        if (! empty($data['default'])) {
            PlatformSetting::set('llm.default', $data['default'], 'llm');
        }

        \Illuminate\Support\Facades\Cache::forget('platform_settings');

        Notification::make()->title('AI integrations saved')->success()->send();
    }

    public function testProvider(string $modelKey): void
    {
        try {
            $registry = app(LlmProviderRegistry::class);
            $start = microtime(true);
            $response = $registry->for($modelKey)->complete(LlmRequest::make(
                'You are a helpful assistant that answers in ONE short sentence.',
                'Please reply with the words "connection ok" and nothing else.',
                ['temperature' => 0.0, 'max_tokens' => 20],
            ));
            $elapsedMs = (int) round((microtime(true) - $start) * 1000);
            $text = trim($response->text);

            Notification::make()
                ->title($modelKey.' — success')
                ->body(sprintf(
                    'Reply: %s · %d ms · %s in / %s out tokens · %d¢',
                    mb_substr($text, 0, 80),
                    $elapsedMs,
                    $response->tokensInput ?? '?',
                    $response->tokensOutput ?? '?',
                    $response->costUsdCents,
                ))
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title($modelKey.' — failed')
                ->body(mb_substr($e->getMessage(), 0, 200))
                ->danger()
                ->send();
        }
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label('Save')->submit('save'),
        ];
    }

    protected static function envKeyForConfig(array $cfg): ?string
    {
        // Best-effort mapping — for display / helper text only.
        return match ($cfg['driver'] ?? null) {
            'claude' => 'ANTHROPIC_API_KEY',
            'gemini' => 'GEMINI_API_KEY',
            'grok' => 'XAI_API_KEY',
            'deepseek' => 'DEEPSEEK_API_KEY',
            'openai' => 'OPENAI_API_KEY',
            default => null,
        };
    }
}
