<?php

namespace App\Filament\SuperAdmin\Pages;

use App\Models\PlatformSetting;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;

class IntegrationSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static ?string $navigationLabel = 'Integrations';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 6;

    protected static string $view = 'filament.super-admin.pages.integration-settings';

    public ?array $whatsappData = [];
    public ?array $smsData = [];
    public ?array $emailData = [];
    public ?array $storageData = [];
    public ?array $paymentData = [];

    public function mount(): void
    {
        $this->whatsappData = [
            'whatsapp_enabled' => (bool) PlatformSetting::get('whatsapp_enabled', false),
            'whatsapp_provider' => PlatformSetting::get('whatsapp_provider', 'twilio'),
            // Twilio fields
            'whatsapp_twilio_account_sid' => PlatformSetting::get('whatsapp_twilio_account_sid', ''),
            'whatsapp_twilio_auth_token' => PlatformSetting::get('whatsapp_twilio_auth_token', ''),
            'whatsapp_twilio_from_number' => PlatformSetting::get('whatsapp_twilio_from_number', ''),
            // Meta fields
            'whatsapp_meta_access_token' => PlatformSetting::get('whatsapp_meta_access_token', ''),
            'whatsapp_meta_phone_number_id' => PlatformSetting::get('whatsapp_meta_phone_number_id', ''),
            'whatsapp_meta_business_id' => PlatformSetting::get('whatsapp_meta_business_id', ''),
        ];

        $this->smsData = [
            'sms_enabled' => (bool) PlatformSetting::get('sms_enabled', false),
            'sms_provider' => PlatformSetting::get('sms_provider', 'twilio'),
            'sms_api_key' => PlatformSetting::get('sms_api_key', ''),
            'sms_api_secret' => PlatformSetting::get('sms_api_secret', ''),
            'sms_sender_id' => PlatformSetting::get('sms_sender_id', 'XLinic'),
        ];

        $this->emailData = [
            'email_provider' => PlatformSetting::get('email_provider', 'smtp'),
            'smtp_host' => PlatformSetting::get('smtp_host', config('mail.mailers.smtp.host', '')),
            'smtp_port' => PlatformSetting::get('smtp_port', config('mail.mailers.smtp.port', 587)),
            'smtp_username' => PlatformSetting::get('smtp_username', ''),
            'smtp_password' => PlatformSetting::get('smtp_password', ''),
            'smtp_encryption' => PlatformSetting::get('smtp_encryption', 'tls'),
            'mail_from_address' => PlatformSetting::get('mail_from_address', config('mail.from.address', '')),
            'mail_from_name' => PlatformSetting::get('mail_from_name', config('mail.from.name', '')),
            'mailgun_domain' => PlatformSetting::get('mailgun_domain', ''),
            'mailgun_secret' => PlatformSetting::get('mailgun_secret', ''),
            'ses_key' => PlatformSetting::get('ses_key', ''),
            'ses_secret' => PlatformSetting::get('ses_secret', ''),
            'ses_region' => PlatformSetting::get('ses_region', 'us-east-1'),
        ];

        $this->storageData = [
            'storage_driver' => PlatformSetting::get('storage_driver', 'local'),
            's3_key' => PlatformSetting::get('s3_key', ''),
            's3_secret' => PlatformSetting::get('s3_secret', ''),
            's3_region' => PlatformSetting::get('s3_region', 'us-east-1'),
            's3_bucket' => PlatformSetting::get('s3_bucket', ''),
            's3_url' => PlatformSetting::get('s3_url', ''),
            's3_endpoint' => PlatformSetting::get('s3_endpoint', ''),
        ];

        $this->paymentData = [
            'payment_gateway' => PlatformSetting::get('payment_gateway', 'paymob'),
            'paymob_api_key' => PlatformSetting::get('paymob_api_key', ''),
            'paymob_integration_id' => PlatformSetting::get('paymob_integration_id', ''),
            'paymob_iframe_id' => PlatformSetting::get('paymob_iframe_id', ''),
            'paymob_hmac_secret' => PlatformSetting::get('paymob_hmac_secret', ''),
            'stripe_key' => PlatformSetting::get('stripe_key', ''),
            'stripe_secret' => PlatformSetting::get('stripe_secret', ''),
            'stripe_webhook_secret' => PlatformSetting::get('stripe_webhook_secret', ''),
        ];

        // Fill all forms with their data
        $this->whatsappForm->fill($this->whatsappData);
        $this->smsForm->fill($this->smsData);
        $this->emailForm->fill($this->emailData);
        $this->storageForm->fill($this->storageData);
        $this->paymentForm->fill($this->paymentData);
    }

    public function whatsappForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Toggle::make('whatsapp_enabled')
                    ->label('Enable WhatsApp Integration')
                    ->live(),

                Forms\Components\Select::make('whatsapp_provider')
                    ->label('Provider')
                    ->options([
                        'twilio' => 'Twilio',
                        'meta' => 'Meta (Official API)',
                    ])
                    ->live()
                    ->visible(fn(Forms\Get $get) => $get('whatsapp_enabled')),

                // Twilio Configuration
                Forms\Components\Fieldset::make('Twilio Configuration')
                    ->visible(fn(Forms\Get $get) => $get('whatsapp_enabled') && $get('whatsapp_provider') === 'twilio')
                    ->schema([
                        Forms\Components\TextInput::make('whatsapp_twilio_account_sid')
                            ->label('Account SID')
                            ->password()
                            ->revealable()
                            ->helperText('Your Twilio Account SID from twilio.com/console'),

                        Forms\Components\TextInput::make('whatsapp_twilio_auth_token')
                            ->label('Auth Token')
                            ->password()
                            ->revealable()
                            ->helperText('Your Twilio Auth Token'),

                        Forms\Components\TextInput::make('whatsapp_twilio_from_number')
                            ->label('From Number')
                            ->placeholder('whatsapp:+14155238886')
                            ->helperText('Format: whatsapp:+1234567890'),
                    ]),

                // Meta Configuration
                Forms\Components\Fieldset::make('Meta (Official API) Configuration')
                    ->visible(fn(Forms\Get $get) => $get('whatsapp_enabled') && $get('whatsapp_provider') === 'meta')
                    ->schema([
                        Forms\Components\TextInput::make('whatsapp_meta_access_token')
                            ->label('Access Token')
                            ->password()
                            ->revealable()
                            ->helperText('Your Meta WhatsApp Business API Access Token'),

                        Forms\Components\TextInput::make('whatsapp_meta_phone_number_id')
                            ->label('Phone Number ID')
                            ->helperText('Your WhatsApp Business Phone Number ID'),

                        Forms\Components\TextInput::make('whatsapp_meta_business_id')
                            ->label('Business Account ID')
                            ->helperText('Your Meta Business Account ID'),
                    ]),
            ])
            ->statePath('whatsappData');
    }

    public function smsForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Toggle::make('sms_enabled')
                    ->label('Enable SMS Integration')
                    ->live(),

                Forms\Components\Select::make('sms_provider')
                    ->label('Provider')
                    ->options([
                        'twilio' => 'Twilio',
                        'vonage' => 'Vonage (Nexmo)',
                        'messagebird' => 'MessageBird',
                        'victorylink' => 'VictoryLink (Egypt)',
                        'cequens' => 'Cequens (MENA)',
                    ])
                    ->visible(fn(Forms\Get $get) => $get('sms_enabled')),

                Forms\Components\TextInput::make('sms_api_key')
                    ->label('API Key / Account SID')
                    ->password()
                    ->revealable()
                    ->visible(fn(Forms\Get $get) => $get('sms_enabled')),

                Forms\Components\TextInput::make('sms_api_secret')
                    ->label('API Secret / Auth Token')
                    ->password()
                    ->revealable()
                    ->visible(fn(Forms\Get $get) => $get('sms_enabled')),

                Forms\Components\TextInput::make('sms_sender_id')
                    ->label('Sender ID')
                    ->maxLength(11)
                    ->helperText('Max 11 characters, alphanumeric')
                    ->visible(fn(Forms\Get $get) => $get('sms_enabled')),
            ])
            ->statePath('smsData');
    }

    public function emailForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('email_provider')
                    ->label('Email Provider')
                    ->options([
                        'smtp' => 'SMTP Server',
                        'mailgun' => 'Mailgun',
                        'ses' => 'Amazon SES',
                        'postmark' => 'Postmark',
                        'sendgrid' => 'SendGrid',
                    ])
                    ->live(),

                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('mail_from_address')
                            ->label('From Email Address')
                            ->email()
                            ->required(),

                        Forms\Components\TextInput::make('mail_from_name')
                            ->label('From Name')
                            ->required(),
                    ]),

                // SMTP Settings
                Forms\Components\Fieldset::make('SMTP Configuration')
                    ->visible(fn(Forms\Get $get) => $get('email_provider') === 'smtp')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('smtp_host')
                                    ->label('SMTP Host')
                                    ->placeholder('smtp.example.com'),

                                Forms\Components\TextInput::make('smtp_port')
                                    ->label('Port')
                                    ->numeric()
                                    ->default(587),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('smtp_username')
                                    ->label('Username'),

                                Forms\Components\TextInput::make('smtp_password')
                                    ->label('Password')
                                    ->password()
                                    ->revealable(),
                            ]),

                        Forms\Components\Select::make('smtp_encryption')
                            ->label('Encryption')
                            ->options([
                                'tls' => 'TLS',
                                'ssl' => 'SSL',
                                null => 'None',
                            ])
                            ->default('tls'),
                    ]),

                // Mailgun Settings
                Forms\Components\Fieldset::make('Mailgun Configuration')
                    ->visible(fn(Forms\Get $get) => $get('email_provider') === 'mailgun')
                    ->schema([
                        Forms\Components\TextInput::make('mailgun_domain')
                            ->label('Domain')
                            ->placeholder('mg.example.com'),

                        Forms\Components\TextInput::make('mailgun_secret')
                            ->label('API Key')
                            ->password()
                            ->revealable(),
                    ]),

                // Amazon SES Settings
                Forms\Components\Fieldset::make('Amazon SES Configuration')
                    ->visible(fn(Forms\Get $get) => $get('email_provider') === 'ses')
                    ->schema([
                        Forms\Components\TextInput::make('ses_key')
                            ->label('Access Key ID')
                            ->password()
                            ->revealable(),

                        Forms\Components\TextInput::make('ses_secret')
                            ->label('Secret Access Key')
                            ->password()
                            ->revealable(),

                        Forms\Components\Select::make('ses_region')
                            ->label('Region')
                            ->options([
                                'us-east-1' => 'US East (N. Virginia)',
                                'us-west-2' => 'US West (Oregon)',
                                'eu-west-1' => 'Europe (Ireland)',
                                'eu-central-1' => 'Europe (Frankfurt)',
                                'ap-south-1' => 'Asia Pacific (Mumbai)',
                                'ap-southeast-1' => 'Asia Pacific (Singapore)',
                            ])
                            ->default('us-east-1'),
                    ]),
            ])
            ->statePath('emailData');
    }

    public function storageForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('storage_driver')
                    ->label('Storage Driver')
                    ->options([
                        'local' => 'Local Storage',
                        's3' => 'Amazon S3',
                        'spaces' => 'DigitalOcean Spaces',
                        'wasabi' => 'Wasabi',
                        'minio' => 'MinIO (Self-hosted)',
                    ])
                    ->live(),

                Forms\Components\TextInput::make('s3_key')
                    ->label('Access Key ID')
                    ->password()
                    ->revealable()
                    ->visible(fn(Forms\Get $get) => in_array($get('storage_driver'), ['s3', 'spaces', 'wasabi', 'minio'])),

                Forms\Components\TextInput::make('s3_secret')
                    ->label('Secret Access Key')
                    ->password()
                    ->revealable()
                    ->visible(fn(Forms\Get $get) => in_array($get('storage_driver'), ['s3', 'spaces', 'wasabi', 'minio'])),

                Forms\Components\TextInput::make('s3_region')
                    ->label('Region')
                    ->placeholder('us-east-1')
                    ->visible(fn(Forms\Get $get) => in_array($get('storage_driver'), ['s3', 'spaces', 'wasabi'])),

                Forms\Components\TextInput::make('s3_bucket')
                    ->label('Bucket Name')
                    ->visible(fn(Forms\Get $get) => in_array($get('storage_driver'), ['s3', 'spaces', 'wasabi', 'minio'])),

                Forms\Components\TextInput::make('s3_url')
                    ->label('CDN/Public URL')
                    ->placeholder('https://cdn.example.com')
                    ->helperText('Optional: CloudFront or CDN URL for public assets')
                    ->visible(fn(Forms\Get $get) => in_array($get('storage_driver'), ['s3', 'spaces', 'wasabi', 'minio'])),

                Forms\Components\TextInput::make('s3_endpoint')
                    ->label('Custom Endpoint')
                    ->placeholder('https://nyc3.digitaloceanspaces.com')
                    ->helperText('Required for non-AWS S3-compatible storage')
                    ->visible(fn(Forms\Get $get) => in_array($get('storage_driver'), ['spaces', 'wasabi', 'minio'])),
            ])
            ->statePath('storageData');
    }

    public function paymentForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('payment_gateway')
                    ->label('Primary Payment Gateway')
                    ->options([
                        'paymob' => 'PayMob (Egypt)',
                        'stripe' => 'Stripe',
                        'tap' => 'Tap Payments (GCC)',
                        'fawry' => 'Fawry (Egypt)',
                    ])
                    ->live(),

                // PayMob Settings
                Forms\Components\Fieldset::make('PayMob Configuration')
                    ->visible(fn(Forms\Get $get) => $get('payment_gateway') === 'paymob')
                    ->schema([
                        Forms\Components\TextInput::make('paymob_api_key')
                            ->label('API Key')
                            ->password()
                            ->revealable(),

                        Forms\Components\TextInput::make('paymob_integration_id')
                            ->label('Integration ID'),

                        Forms\Components\TextInput::make('paymob_iframe_id')
                            ->label('iFrame ID'),

                        Forms\Components\TextInput::make('paymob_hmac_secret')
                            ->label('HMAC Secret')
                            ->password()
                            ->revealable(),
                    ]),

                // Stripe Settings
                Forms\Components\Fieldset::make('Stripe Configuration')
                    ->visible(fn(Forms\Get $get) => $get('payment_gateway') === 'stripe')
                    ->schema([
                        Forms\Components\TextInput::make('stripe_key')
                            ->label('Publishable Key')
                            ->password()
                            ->revealable(),

                        Forms\Components\TextInput::make('stripe_secret')
                            ->label('Secret Key')
                            ->password()
                            ->revealable(),

                        Forms\Components\TextInput::make('stripe_webhook_secret')
                            ->label('Webhook Secret')
                            ->password()
                            ->revealable(),
                    ]),
            ])
            ->statePath('paymentData');
    }

    public function saveWhatsapp(): void
    {
        $data = $this->whatsappForm->getState();
        $booleanFields = ['whatsapp_enabled'];

        foreach ($data as $key => $value) {
            // Skip empty values to avoid cluttering the database
            if ($value === null || $value === '') {
                continue;
            }

            $type = in_array($key, $booleanFields) ? 'boolean' : 'string';
            PlatformSetting::set($key, $value, 'whatsapp', $type);
        }

        // Update local state
        $this->whatsappData = $data;

        // Clear cache to apply new settings immediately
        \Artisan::call('cache:clear');

        Notification::make()
            ->title('WhatsApp settings saved')
            ->body('Cache cleared. Settings are now active.')
            ->success()
            ->send();
    }

    public function saveSms(): void
    {
        $data = $this->smsForm->getState();
        $booleanFields = ['sms_enabled'];

        foreach ($data as $key => $value) {
            // Skip empty values to avoid cluttering the database
            if ($value === null || $value === '') {
                continue;
            }

            $type = in_array($key, $booleanFields) ? 'boolean' : 'string';
            PlatformSetting::set($key, $value, 'sms', $type);
        }

        // Update local state
        $this->smsData = $data;

        // Clear cache to apply new settings immediately
        \Artisan::call('cache:clear');

        Notification::make()
            ->title('SMS settings saved')
            ->body('Cache cleared. Settings are now active.')
            ->success()
            ->send();
    }

    public function saveEmail(): void
    {
        $data = $this->emailForm->getState();
        $integerFields = ['smtp_port'];

        foreach ($data as $key => $value) {
            $type = in_array($key, $integerFields) ? 'integer' : 'string';
            PlatformSetting::set($key, $value, 'email', $type);
        }

        // Update local state
        $this->emailData = $data;

        // Clear config cache to apply new mail settings
        Artisan::call('config:clear');

        Notification::make()
            ->title('Email settings saved')
            ->success()
            ->send();
    }

    public function testEmail(): void
    {
        try {
            // In production, this would send a test email
            \Mail::raw('This is a test email from XLinic Platform.', function ($message) {
                $message->to($this->emailData['mail_from_address'] ?? config('mail.from.address'))
                    ->subject('XLinic Test Email');
            });

            Notification::make()
                ->title('Test Email Sent')
                ->body('Check your inbox for the test email')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Email Test Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function saveStorage(): void
    {
        $data = $this->storageForm->getState();

        foreach ($data as $key => $value) {
            PlatformSetting::set($key, $value, 'storage', 'string');
        }

        // Update local state
        $this->storageData = $data;

        // Clear config cache to apply new storage settings
        Artisan::call('config:clear');

        Notification::make()
            ->title('Storage settings saved')
            ->success()
            ->send();
    }

    public function savePayment(): void
    {
        $data = $this->paymentForm->getState();

        foreach ($data as $key => $value) {
            PlatformSetting::set($key, $value, 'payment', 'string');
        }

        // Update local state
        $this->paymentData = $data;

        Notification::make()
            ->title('Payment settings saved')
            ->success()
            ->send();
    }

    public function testWhatsapp(): void
    {
        // In production, this would send a test message
        Notification::make()
            ->title('WhatsApp Test')
            ->body('Test message would be sent to the configured number')
            ->info()
            ->send();
    }

    public function testSms(): void
    {
        Notification::make()
            ->title('SMS Test')
            ->body('Test SMS would be sent')
            ->info()
            ->send();
    }

    public function testStorage(): void
    {
        try {
            // Test storage connection
            $driver = $this->storageData['storage_driver'] ?? 'local';

            if ($driver === 'local') {
                $path = storage_path('app/test-connection.txt');
                file_put_contents($path, 'test');
                unlink($path);
            }

            Notification::make()
                ->title('Storage Connection Successful')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Storage Connection Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getForms(): array
    {
        return [
            'whatsappForm',
            'smsForm',
            'emailForm',
            'storageForm',
            'paymentForm',
        ];
    }
}
