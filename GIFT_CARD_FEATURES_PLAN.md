# Gift Card Features Plan

## Overview

This document outlines the implementation plan for adding missing gift card features to match the old system, following X-Linic's established architecture patterns.

---

## Current vs Required Features

| Feature | Current | Old System | Action |
|---------|---------|------------|--------|
| Basic CRUD | ✅ | ✅ | Done |
| Status Management | ✅ | ✅ | Done |
| Transaction History | ✅ | ✅ | Done |
| Auto-activation on Invoice Paid | ✅ | ✅ | Done |
| Gift Card Templates | ✅ | ✅ | **COMPLETED** |
| Batch Generation | ✅ | ✅ | **COMPLETED** |
| Staff Assignment | ✅ | ✅ | **COMPLETED** |
| GL/Accounting Integration | ✅ | ✅ | **COMPLETED** |
| Secure Code Generation | ✅ | ✅ | **COMPLETED** |
| Print/Export | ✅ | ✅ | **COMPLETED** |
| Staff Dashboard | ✅ | ✅ | **COMPLETED** |
| Multi-card Payment | ⏳ | ✅ | Partial (validation ready, UI pending) |

---

## System Patterns to Follow

### Module Structure
```
modules/GiftCards/
├── Config/config.php
├── Database/Migrations/
├── Filament/
│   ├── Resources/
│   │   ├── GiftCardResource.php
│   │   └── GiftCardTemplateResource.php     ← NEW
│   └── Pages/
│       └── StaffGiftCardDashboard.php       ← NEW
├── Lang/{en,ar}/
├── Models/
│   ├── GiftCard.php
│   ├── GiftCardTransaction.php
│   ├── GiftCardTemplate.php                  ← NEW
│   ├── GiftCardPrintHistory.php              ← NEW
│   └── GiftCardBatchExport.php               ← NEW
├── Providers/
├── Routes/
├── Services/
│   ├── GiftCardService.php                   ← NEW
│   ├── GiftCardGeneratorService.php          ← NEW
│   ├── GiftCardGLService.php                 ← NEW
│   └── GiftCardExportService.php             ← NEW
└── module.json
```

### Model Pattern
```php
class Model extends BaseModel
{
    use HasTenancy, HasActivity, HasSequence, SoftDeletes;

    protected string $sequenceCode = 'xxx';
    protected string $sequenceColumn = 'code';

    // Status constants
    public const STATUS_XXX = 'xxx';

    // State transitions
    public function canTransitionTo(string $status): bool;
    public function transitionTo(string $status): bool;
}
```

### Service Pattern
```php
class XxxService
{
    protected AccountingIntegrationService $accountingService;
    protected DefaultAccountsService $defaultAccounts;

    public function __construct(AccountingIntegrationService $accountingService)
    {
        $this->accountingService = $accountingService;
        $this->defaultAccounts = new DefaultAccountsService();
    }
}
```

### Filament Resource Pattern
```php
class XxxResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $moduleCode = 'giftcards';
    protected static ?string $permissionKey = 'gift_card_templates';
}
```

---

## Implementation Phases

### Phase 1: Gift Card Templates
> Foundation for all other features

#### 1.1 Database Migration: `gc_templates`

**File:** `Database/Migrations/2024_01_02_000001_create_gc_templates_table.php`

```php
Schema::create('gc_templates', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('tenant_id')->index();
    $table->string('code')->index();
    $table->string('name');
    $table->text('description')->nullable();

    // Value configuration
    $table->integer('min_amount_minor')->default(10000);  // 100.00
    $table->integer('max_amount_minor')->default(100000000);  // 1,000,000.00
    $table->json('preset_amounts')->nullable();  // [5000, 10000, 25000, 50000]

    // Validity
    $table->integer('validity_days')->default(365);

    // Discount
    $table->string('discount_type')->nullable();  // percentage, fixed, null
    $table->integer('discount_value')->default(0);

    // Card design
    $table->json('card_design')->nullable();  // colors, logo, etc.

    // Behavior
    $table->boolean('allow_partial_redemption')->default(true);
    $table->boolean('requires_activation')->default(true);
    $table->boolean('is_active')->default(true);

    // GL Accounts (FK to chart_of_accounts)
    $table->uuid('liability_account_id')->nullable();
    $table->uuid('revenue_account_id')->nullable();
    $table->uuid('expense_account_id')->nullable();
    $table->uuid('breakage_account_id')->nullable();
    $table->uuid('sales_journal_id')->nullable();

    $table->uuid('created_by_user_id')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->index(['tenant_id', 'code']);
    $table->index(['tenant_id', 'is_active']);
});
```

#### 1.2 GiftCardTemplate Model

**File:** `Models/GiftCardTemplate.php`

```php
class GiftCardTemplate extends BaseModel
{
    use HasTenancy, HasActivity, HasSequence, SoftDeletes;

    protected string $sequenceCode = 'gc_template';
    protected string $sequenceColumn = 'code';

    protected $table = 'gc_templates';

    protected $fillable = [
        'tenant_id', 'code', 'name', 'description',
        'min_amount_minor', 'max_amount_minor', 'preset_amounts',
        'validity_days', 'discount_type', 'discount_value',
        'card_design', 'allow_partial_redemption', 'requires_activation',
        'is_active', 'liability_account_id', 'revenue_account_id',
        'expense_account_id', 'breakage_account_id', 'sales_journal_id',
        'created_by_user_id',
    ];

    protected $casts = [
        'preset_amounts' => 'array',
        'card_design' => 'array',
        'min_amount_minor' => 'integer',
        'max_amount_minor' => 'integer',
        'validity_days' => 'integer',
        'discount_value' => 'integer',
        'allow_partial_redemption' => 'boolean',
        'requires_activation' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Discount types
    public const DISCOUNT_PERCENTAGE = 'percentage';
    public const DISCOUNT_FIXED = 'fixed';

    // Relationships
    public function giftCards(): HasMany;
    public function liabilityAccount(): BelongsTo;
    public function revenueAccount(): BelongsTo;
    public function expenseAccount(): BelongsTo;
    public function breakageAccount(): BelongsTo;
    public function salesJournal(): BelongsTo;
    public function createdBy(): BelongsTo;

    // Scopes
    public function scopeActive(Builder $query): Builder;

    // Methods
    public function getDiscountedPrice(int $amountMinor): int;
    public function getPresetAmountsFormatted(): array;
    public function calculateExpiryDate(): Carbon;

    // Statistics
    public function getStatistics(): array;
}
```

#### 1.3 Update GiftCard Model

**Add to migration:** `add_template_to_gift_cards.php`

```php
$table->uuid('template_id')->nullable()->after('tenant_id');
$table->uuid('assigned_to_staff_id')->nullable();
$table->timestamp('assigned_at')->nullable();
$table->uuid('sold_by_staff_id')->nullable();
$table->string('encrypted_code')->nullable();
$table->string('code_hash')->nullable()->index();
$table->string('pin_code')->nullable();
$table->uuid('journal_entry_id')->nullable();

$table->index(['tenant_id', 'template_id']);
$table->index(['tenant_id', 'assigned_to_staff_id']);
```

**Update GiftCard model relationships:**
```php
public function template(): BelongsTo;
public function assignedToStaff(): BelongsTo;
public function soldByStaff(): BelongsTo;
public function journalEntry(): BelongsTo;

// New scopes
public function scopeAssignedTo(Builder $query, string $staffId): Builder;
public function scopeUnassigned(Builder $query): Builder;
public function scopeByTemplate(Builder $query, string $templateId): Builder;
```

#### 1.4 GiftCardTemplateResource

**File:** `Filament/Resources/GiftCardTemplateResource.php`

```php
class GiftCardTemplateResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = GiftCardTemplate::class;
    protected static ?string $moduleCode = 'giftcards';
    protected static ?string $permissionKey = 'gift_card_templates';
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Marketing';
    protected static ?int $navigationSort = 11;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('giftcards::giftcards.template.basic'))
                ->schema([
                    Forms\Components\TextInput::make('name')->required(),
                    Forms\Components\Textarea::make('description'),
                    Forms\Components\Toggle::make('is_active')->default(true),
                ]),

            Forms\Components\Section::make(__('giftcards::giftcards.template.value_config'))
                ->schema([
                    Forms\Components\TextInput::make('min_amount_minor')
                        ->numeric()
                        ->formatStateUsing(fn ($state) => $state / 100)
                        ->dehydrateStateUsing(fn ($state) => (int)($state * 100)),
                    Forms\Components\TextInput::make('max_amount_minor')
                        ->numeric()
                        ->formatStateUsing(fn ($state) => $state / 100)
                        ->dehydrateStateUsing(fn ($state) => (int)($state * 100)),
                    Forms\Components\TagsInput::make('preset_amounts')
                        ->hint('Enter amounts in major units'),
                    Forms\Components\TextInput::make('validity_days')
                        ->numeric()
                        ->default(365),
                ])->columns(2),

            Forms\Components\Section::make(__('giftcards::giftcards.template.discount'))
                ->schema([
                    Forms\Components\Select::make('discount_type')
                        ->options([
                            'percentage' => 'Percentage',
                            'fixed' => 'Fixed Amount',
                        ]),
                    Forms\Components\TextInput::make('discount_value')
                        ->numeric()
                        ->default(0),
                ])->columns(2),

            Forms\Components\Section::make(__('giftcards::giftcards.template.gl_accounts'))
                ->schema([
                    Forms\Components\Select::make('liability_account_id')
                        ->label('Gift Card Liability Account')
                        ->relationship('liabilityAccount', 'name')
                        ->searchable()
                        ->preload(),
                    Forms\Components\Select::make('revenue_account_id')
                        ->label('Redemption Revenue Account')
                        ->relationship('revenueAccount', 'name')
                        ->searchable()
                        ->preload(),
                    Forms\Components\Select::make('breakage_account_id')
                        ->label('Breakage Revenue Account')
                        ->relationship('breakageAccount', 'name')
                        ->searchable()
                        ->preload(),
                    Forms\Components\Select::make('sales_journal_id')
                        ->label('Sales Journal')
                        ->options(fn () => Journal::active()->pluck('name', 'id'))
                        ->searchable(),
                ])->columns(2),

            Forms\Components\Section::make(__('giftcards::giftcards.template.behavior'))
                ->schema([
                    Forms\Components\Toggle::make('allow_partial_redemption')->default(true),
                    Forms\Components\Toggle::make('requires_activation')->default(true),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('gift_cards_count')
                    ->counts('giftCards')
                    ->label('Cards'),
                Tables\Columns\TextColumn::make('validity_days')->suffix(' days'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('generate_batch')
                    ->label('Generate Batch')
                    ->icon('heroicon-o-squares-plus')
                    ->color('success')
                    ->form([
                        Forms\Components\TextInput::make('quantity')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(100)
                            ->default(10),
                        Forms\Components\TextInput::make('value_minor')
                            ->label('Card Value')
                            ->numeric()
                            ->required()
                            ->formatStateUsing(fn ($state) => $state / 100)
                            ->dehydrateStateUsing(fn ($state) => (int)($state * 100)),
                    ])
                    ->action(function (GiftCardTemplate $record, array $data) {
                        app(GiftCardService::class)->generateBatch(
                            $record,
                            $data['quantity'],
                            $data['value_minor']
                        );
                        Notification::make()
                            ->title("Generated {$data['quantity']} gift cards")
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
```

---

### Phase 2: GL/Accounting Integration
> Critical for financial accuracy

#### 2.1 Add Default Accounts

**Update:** `modules/Accounting/Services/DefaultAccountsService.php`

```php
protected array $fallbackCodes = [
    // ... existing codes ...
    'default_gift_card_liability_account_id' => ['2040', '2000'],
    'default_gift_card_breakage_account_id' => ['4050', '4000'],
];

public function getGiftCardLiabilityAccount(): ?ChartOfAccount
{
    return $this->getAccount('default_gift_card_liability_account_id');
}

public function getGiftCardBreakageAccount(): ?ChartOfAccount
{
    return $this->getAccount('default_gift_card_breakage_account_id');
}
```

**Update:** `modules/Accounting/Filament/Pages/DefaultAccountsPage.php`

Add Gift Card section with:
- Gift Card Liability Account
- Gift Card Breakage Revenue Account

**Update:** `modules/Accounting/Database/Seeders/ChartOfAccountsSeeder.php`

```php
// Add to liability accounts
['code' => '2040', 'name' => 'Gift Card Liability', 'type' => 'liability'],

// Add to revenue accounts
['code' => '4050', 'name' => 'Gift Card Breakage Revenue', 'type' => 'income'],
```

#### 2.2 GiftCardGLService

**File:** `Services/GiftCardGLService.php`

```php
class GiftCardGLService
{
    protected AccountingIntegrationService $accountingService;
    protected DefaultAccountsService $defaultAccounts;

    public function __construct(AccountingIntegrationService $accountingService)
    {
        $this->accountingService = $accountingService;
        $this->defaultAccounts = new DefaultAccountsService();
    }

    /**
     * Post gift card sale journal entry
     * DR: Cash/Bank (payment method)
     * CR: Gift Card Liability
     */
    public function postGiftCardSale(
        GiftCard $card,
        Payment $payment,
        ?int $discountMinor = null
    ): ?JournalEntry {
        $template = $card->template;

        // Get accounts
        $liabilityAccount = $template?->liabilityAccount
            ?? $this->defaultAccounts->getGiftCardLiabilityAccount();
        $cashAccount = $this->defaultAccounts->getCashAccount();

        if (!$liabilityAccount || !$cashAccount) {
            Log::warning('Gift card GL accounts not configured');
            return null;
        }

        $lines = [
            [
                'account_code' => $cashAccount->code,
                'debit' => $payment->amount_minor,
                'credit' => 0,
                'description' => "Gift card sale: {$card->code}",
            ],
            [
                'account_code' => $liabilityAccount->code,
                'debit' => 0,
                'credit' => $card->initial_value_minor,
                'description' => "Gift card liability: {$card->code}",
            ],
        ];

        // Handle discount if sold below face value
        if ($discountMinor && $discountMinor > 0) {
            $expenseAccount = $template?->expenseAccount
                ?? $this->defaultAccounts->getDiscountAccount();

            if ($expenseAccount) {
                $lines[] = [
                    'account_code' => $expenseAccount->code,
                    'debit' => $discountMinor,
                    'credit' => 0,
                    'description' => "Gift card discount: {$card->code}",
                ];
            }
        }

        return $this->accountingService->createJournalEntry(
            now(),
            "Gift card sale: {$card->code}",
            $lines,
            GiftCard::class,
            $card->id,
            true
        );
    }

    /**
     * Post gift card redemption journal entry
     * DR: Gift Card Liability
     * CR: Service Revenue (from invoice line accounts)
     */
    public function postGiftCardRedemption(
        GiftCard $card,
        int $amountMinor,
        ?Invoice $invoice = null
    ): ?JournalEntry {
        $template = $card->template;

        $liabilityAccount = $template?->liabilityAccount
            ?? $this->defaultAccounts->getGiftCardLiabilityAccount();
        $revenueAccount = $template?->revenueAccount
            ?? $this->defaultAccounts->getServiceRevenueAccount();

        if (!$liabilityAccount || !$revenueAccount) {
            return null;
        }

        $lines = [
            [
                'account_code' => $liabilityAccount->code,
                'debit' => $amountMinor,
                'credit' => 0,
                'description' => "Redemption: {$card->code}",
            ],
            [
                'account_code' => $revenueAccount->code,
                'debit' => 0,
                'credit' => $amountMinor,
                'description' => $invoice
                    ? "Invoice {$invoice->code}"
                    : "Gift card redemption",
            ],
        ];

        return $this->accountingService->createJournalEntry(
            now(),
            "Gift card redemption: {$card->code}" . ($invoice ? " - Invoice {$invoice->code}" : ''),
            $lines,
            GiftCard::class,
            $card->id,
            true
        );
    }

    /**
     * Post gift card expiration journal entry
     * DR: Gift Card Liability
     * CR: Breakage Revenue
     */
    public function postGiftCardExpiration(GiftCard $card): ?JournalEntry
    {
        if ($card->remaining_value_minor <= 0) {
            return null;
        }

        $template = $card->template;

        $liabilityAccount = $template?->liabilityAccount
            ?? $this->defaultAccounts->getGiftCardLiabilityAccount();
        $breakageAccount = $template?->breakageAccount
            ?? $this->defaultAccounts->getGiftCardBreakageAccount();

        if (!$liabilityAccount || !$breakageAccount) {
            return null;
        }

        $lines = [
            [
                'account_code' => $liabilityAccount->code,
                'debit' => $card->remaining_value_minor,
                'credit' => 0,
                'description' => "Expiration: {$card->code}",
            ],
            [
                'account_code' => $breakageAccount->code,
                'debit' => 0,
                'credit' => $card->remaining_value_minor,
                'description' => "Breakage revenue: {$card->code}",
            ],
        ];

        return $this->accountingService->createJournalEntry(
            now(),
            "Gift card expired: {$card->code}",
            $lines,
            GiftCard::class,
            $card->id,
            true
        );
    }
}
```

#### 2.3 Update GiftCardTransaction Model

**Migration:** `add_journal_entry_to_gc_transactions.php`

```php
$table->uuid('journal_entry_id')->nullable()->after('payment_id');
```

**Add relationship:**
```php
public function journalEntry(): BelongsTo
{
    return $this->belongsTo(JournalEntry::class);
}
```

---

### Phase 3: Batch Generation & Security
> Secure card code generation

#### 3.1 GiftCardGeneratorService

**File:** `Services/GiftCardGeneratorService.php`

```php
class GiftCardGeneratorService
{
    /**
     * Generate secure gift card serial number
     * Format: 12 digits + 2 check digits = XXXX-XXXX-XXXX-XX
     */
    public function generateSerialNumber(): string
    {
        // Generate 12 random digits
        $digits = '';
        for ($i = 0; $i < 12; $i++) {
            $digits .= random_int(0, 9);
        }

        // Calculate 2 Luhn check digits
        $checkDigits = $this->calculateLuhnCheckDigits($digits);

        return $digits . $checkDigits;
    }

    /**
     * Format card number for display
     */
    public function formatCardNumber(string $serial): string
    {
        // XXXX-XXXX-XXXX-XX
        return sprintf(
            '%s-%s-%s-%s',
            substr($serial, 0, 4),
            substr($serial, 4, 4),
            substr($serial, 8, 4),
            substr($serial, 12, 2)
        );
    }

    /**
     * Validate card number using Luhn algorithm
     */
    public function validateCardNumber(string $serial): bool
    {
        $serial = preg_replace('/[^0-9]/', '', $serial);

        if (strlen($serial) !== 14) {
            return false;
        }

        $digits = substr($serial, 0, 12);
        $checkDigits = substr($serial, 12, 2);

        return $checkDigits === $this->calculateLuhnCheckDigits($digits);
    }

    /**
     * Generate optional PIN code
     */
    public function generatePinCode(int $length = 4): string
    {
        $pin = '';
        for ($i = 0; $i < $length; $i++) {
            $pin .= random_int(0, 9);
        }
        return $pin;
    }

    /**
     * Encrypt serial code for storage
     */
    public function encryptCode(string $code): string
    {
        return Crypt::encryptString($code);
    }

    /**
     * Decrypt serial code
     */
    public function decryptCode(string $encrypted): string
    {
        return Crypt::decryptString($encrypted);
    }

    /**
     * Generate hash for lookup
     */
    public function hashCode(string $code): string
    {
        return hash('sha256', $code);
    }

    protected function calculateLuhnCheckDigits(string $digits): string
    {
        // Luhn algorithm implementation
        $sum = 0;
        $length = strlen($digits);

        for ($i = 0; $i < $length; $i++) {
            $digit = (int) $digits[$length - 1 - $i];

            if ($i % 2 === 1) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
        }

        $checkDigit1 = (10 - ($sum % 10)) % 10;
        $checkDigit2 = ($sum + $checkDigit1) % 10;

        return $checkDigit1 . $checkDigit2;
    }
}
```

#### 3.2 GiftCardService

**File:** `Services/GiftCardService.php`

```php
class GiftCardService
{
    protected GiftCardGeneratorService $generator;
    protected GiftCardGLService $glService;

    public function __construct(
        GiftCardGeneratorService $generator,
        GiftCardGLService $glService
    ) {
        $this->generator = $generator;
        $this->glService = $glService;
    }

    /**
     * Generate batch of gift cards from template
     */
    public function generateBatch(
        GiftCardTemplate $template,
        int $quantity,
        int $valueMinor,
        ?array $options = []
    ): Collection {
        $cards = collect();

        DB::transaction(function () use ($template, $quantity, $valueMinor, $options, &$cards) {
            for ($i = 0; $i < $quantity; $i++) {
                $serial = $this->generator->generateSerialNumber();

                $card = GiftCard::create([
                    'tenant_id' => $template->tenant_id,
                    'template_id' => $template->id,
                    'code' => $this->generator->formatCardNumber($serial),
                    'encrypted_code' => $this->generator->encryptCode($serial),
                    'code_hash' => $this->generator->hashCode($serial),
                    'initial_value_minor' => $valueMinor,
                    'remaining_value_minor' => $valueMinor,
                    'status' => $template->requires_activation
                        ? GiftCard::STATUS_DRAFT
                        : GiftCard::STATUS_ACTIVE,
                    'expires_at' => $template->calculateExpiryDate(),
                    'pin_code' => $options['generate_pin'] ?? false
                        ? $this->generator->generatePinCode()
                        : null,
                ]);

                $cards->push($card);
            }

            // Create batch export record
            GiftCardBatchExport::create([
                'tenant_id' => $template->tenant_id,
                'template_id' => $template->id,
                'quantity' => $quantity,
                'card_ids' => $cards->pluck('id')->toArray(),
                'exported_by' => auth()->id(),
            ]);
        });

        return $cards;
    }

    /**
     * Assign cards to staff member
     */
    public function assignToStaff(Collection $cards, string $staffId): int
    {
        $count = 0;

        foreach ($cards as $card) {
            if ($card->isDraft() && !$card->assigned_to_staff_id) {
                $card->update([
                    'assigned_to_staff_id' => $staffId,
                    'assigned_at' => now(),
                ]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Process gift card sale with GL entry
     */
    public function processSale(
        GiftCard $card,
        string $paymentMethodId,
        ?string $purchaserPatientId = null,
        ?string $recipientPatientId = null,
        ?int $actualPaidMinor = null
    ): array {
        $actualPaid = $actualPaidMinor ?? $card->initial_value_minor;
        $discount = $card->initial_value_minor - $actualPaid;

        DB::transaction(function () use ($card, $paymentMethodId, $purchaserPatientId, $recipientPatientId, $actualPaid, $discount) {
            // Create payment record
            $payment = Payment::create([
                'tenant_id' => $card->tenant_id,
                'amount_minor' => $actualPaid,
                'payment_method_id' => $paymentMethodId,
                'notes' => "Gift card sale: {$card->code}",
            ]);

            // Update card
            $card->update([
                'purchaser_patient_id' => $purchaserPatientId,
                'recipient_patient_id' => $recipientPatientId,
                'sold_by_staff_id' => auth()->id(),
                'status' => GiftCard::STATUS_ACTIVE,
                'activated_at' => now(),
            ]);

            // Create GL entry
            $journalEntry = $this->glService->postGiftCardSale($card, $payment, $discount);

            if ($journalEntry) {
                $card->update(['journal_entry_id' => $journalEntry->id]);
            }

            // Record transaction
            GiftCardTransaction::create([
                'tenant_id' => $card->tenant_id,
                'gift_card_id' => $card->id,
                'type' => GiftCardTransaction::TYPE_ACTIVATE,
                'amount_minor' => $card->initial_value_minor,
                'running_balance_minor' => $card->initial_value_minor,
                'notes' => 'Card sold and activated',
                'created_by_user_id' => auth()->id(),
            ]);
        });

        return [
            'success' => true,
            'card' => $card->fresh(),
        ];
    }

    /**
     * Redeem gift card with GL entry
     */
    public function redeem(
        GiftCard $card,
        int $amountMinor,
        ?Invoice $invoice = null,
        ?Payment $payment = null
    ): array {
        if (!$card->canRedeem()) {
            return ['success' => false, 'error' => 'Card cannot be redeemed'];
        }

        $amountToRedeem = min($amountMinor, $card->remaining_value_minor);

        DB::transaction(function () use ($card, $amountToRedeem, $invoice, $payment) {
            // Update balance
            $newBalance = $card->remaining_value_minor - $amountToRedeem;
            $newStatus = $newBalance > 0
                ? GiftCard::STATUS_PARTIALLY_USED
                : GiftCard::STATUS_FULLY_USED;

            $card->update([
                'remaining_value_minor' => $newBalance,
                'status' => $newStatus,
            ]);

            // Create GL entry
            $journalEntry = $this->glService->postGiftCardRedemption(
                $card,
                $amountToRedeem,
                $invoice
            );

            // Record transaction
            GiftCardTransaction::create([
                'tenant_id' => $card->tenant_id,
                'gift_card_id' => $card->id,
                'type' => GiftCardTransaction::TYPE_REDEEM,
                'amount_minor' => -$amountToRedeem,
                'running_balance_minor' => $newBalance,
                'invoice_id' => $invoice?->id,
                'payment_id' => $payment?->id,
                'journal_entry_id' => $journalEntry?->id,
                'created_by_user_id' => auth()->id(),
            ]);
        });

        return [
            'success' => true,
            'redeemed_amount' => $amountToRedeem,
            'remaining_balance' => $card->fresh()->remaining_value_minor,
        ];
    }

    /**
     * Find card by serial (using hash lookup)
     */
    public function findBySerial(string $serial): ?GiftCard
    {
        $cleanSerial = preg_replace('/[^0-9]/', '', $serial);
        $hash = $this->generator->hashCode($cleanSerial);

        return GiftCard::where('code_hash', $hash)->first();
    }

    /**
     * Get staff statistics
     */
    public function getStaffStatistics(string $staffId, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $query = GiftCard::where('assigned_to_staff_id', $staffId);

        if ($from) {
            $query->where('assigned_at', '>=', $from);
        }
        if ($to) {
            $query->where('assigned_at', '<=', $to);
        }

        $assigned = $query->count();
        $sold = (clone $query)->whereNotNull('sold_by_staff_id')->count();
        $available = (clone $query)->where('status', GiftCard::STATUS_DRAFT)->count();
        $totalSalesValue = (clone $query)
            ->whereNotNull('sold_by_staff_id')
            ->sum('initial_value_minor');

        return [
            'assigned' => $assigned,
            'sold' => $sold,
            'available' => $available,
            'total_sales_value' => $totalSalesValue,
        ];
    }
}
```

---

### Phase 4: Staff Assignment & Dashboard

#### 4.1 Staff Dashboard Page

**File:** `Filament/Pages/StaffGiftCardDashboard.php`

```php
class StaffGiftCardDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-gift';
    protected static ?string $navigationGroup = 'Marketing';
    protected static ?int $navigationSort = 13;
    protected static string $view = 'giftcards::filament.pages.staff-dashboard';

    public function getViewData(): array
    {
        $staffId = auth()->user()->staffProfile?->id;

        if (!$staffId) {
            return ['hasAccess' => false];
        }

        $service = app(GiftCardService::class);
        $stats = $service->getStaffStatistics($staffId);

        $availableCards = GiftCard::where('assigned_to_staff_id', $staffId)
            ->where('status', GiftCard::STATUS_DRAFT)
            ->with('template')
            ->get()
            ->groupBy('template.name');

        return [
            'hasAccess' => true,
            'stats' => $stats,
            'availableCards' => $availableCards,
        ];
    }
}
```

#### 4.2 Update GiftCardResource

Add bulk assign action:

```php
Tables\Actions\BulkAction::make('assign_to_staff')
    ->label('Assign to Staff')
    ->icon('heroicon-o-user-plus')
    ->form([
        Forms\Components\Select::make('staff_id')
            ->label('Staff Member')
            ->options(fn () => StaffProfile::with('user')
                ->get()
                ->pluck('user.name', 'id'))
            ->required(),
    ])
    ->action(function (Collection $records, array $data) {
        $count = app(GiftCardService::class)
            ->assignToStaff($records, $data['staff_id']);

        Notification::make()
            ->title("Assigned {$count} cards to staff")
            ->success()
            ->send();
    })
    ->deselectRecordsAfterCompletion(),
```

---

### Phase 5: Print & Export

#### 5.1 GiftCardExportService

**File:** `Services/GiftCardExportService.php`

```php
class GiftCardExportService
{
    protected GiftCardGeneratorService $generator;

    public function __construct(GiftCardGeneratorService $generator)
    {
        $this->generator = $generator;
    }

    /**
     * Export cards to CSV (streaming)
     */
    public function exportToCsv(Collection $cards): StreamedResponse
    {
        return response()->streamDownload(function () use ($cards) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Code', 'Value', 'Status', 'Expires At', 'PIN']);

            foreach ($cards as $card) {
                fputcsv($handle, [
                    $card->code,
                    $card->initial_value_minor / 100,
                    $card->status,
                    $card->expires_at?->format('Y-m-d'),
                    $card->pin_code,
                ]);
            }

            fclose($handle);
        }, 'gift-cards-' . now()->format('Y-m-d') . '.csv');
    }

    /**
     * Generate single card PDF
     */
    public function generateCardPdf(GiftCard $card): string
    {
        $pdf = Pdf::loadView('giftcards::pdf.gift-card', [
            'card' => $card,
            'template' => $card->template,
            'qrCode' => $this->generateQrCode($card),
        ]);

        return $pdf->output();
    }

    /**
     * Record print history
     */
    public function recordPrint(
        GiftCard $card,
        string $format,
        ?string $printerName = null
    ): GiftCardPrintHistory {
        return GiftCardPrintHistory::create([
            'tenant_id' => $card->tenant_id,
            'gift_card_id' => $card->id,
            'print_format' => $format,
            'printer_name' => $printerName,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'printed_by' => auth()->id(),
        ]);
    }

    protected function generateQrCode(GiftCard $card): string
    {
        // Generate QR code with card validation URL
        return QrCode::size(100)->generate(
            route('gift-cards.validate', ['code' => $card->code])
        );
    }
}
```

#### 5.2 Print History Model & Migration

**Migration:** `create_gc_print_history_table.php`

```php
Schema::create('gc_print_history', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('tenant_id')->index();
    $table->uuid('gift_card_id')->index();
    $table->string('print_format');  // pdf, physical, email
    $table->string('printer_name')->nullable();
    $table->string('ip_address')->nullable();
    $table->text('user_agent')->nullable();
    $table->uuid('printed_by')->nullable();
    $table->timestamps();

    $table->foreign('gift_card_id')
        ->references('id')
        ->on('gift_cards')
        ->cascadeOnDelete();
});
```

---

### Phase 6: Update module.json

**Add new permissions:**
```json
{
  "permissions": {
    "gift_cards.view_any": "View gift cards list",
    "gift_cards.view": "View gift card details",
    "gift_cards.create": "Create gift cards",
    "gift_cards.update": "Update gift cards",
    "gift_cards.delete": "Delete gift cards",
    "gift_cards.redeem": "Redeem gift cards",
    "gift_cards.transactions": "View gift card transactions",
    "gift_card_templates.view_any": "View gift card templates",
    "gift_card_templates.view": "View template details",
    "gift_card_templates.create": "Create templates",
    "gift_card_templates.update": "Update templates",
    "gift_card_templates.delete": "Delete templates",
    "gift_card_templates.generate_batch": "Generate card batches",
    "gift_cards.assign_staff": "Assign cards to staff",
    "gift_cards.staff_dashboard": "Access staff dashboard",
    "gift_cards.export": "Export gift cards"
  }
}
```

**Add new sequences:**
```json
{
  "sequences": [
    {
      "code": "gift_card",
      "prefix": "GC-",
      "padding": 8
    },
    {
      "code": "gc_template",
      "prefix": "GCT-",
      "padding": 4
    },
    {
      "code": "gc_batch",
      "prefix": "GCB-",
      "padding": 6
    }
  ]
}
```

---

## Files to Create

| File | Purpose |
|------|---------|
| `Database/Migrations/2024_01_02_000001_create_gc_templates_table.php` | Templates table |
| `Database/Migrations/2024_01_02_000002_add_template_to_gift_cards.php` | Add fields to gift_cards |
| `Database/Migrations/2024_01_02_000003_create_gc_print_history_table.php` | Print history |
| `Database/Migrations/2024_01_02_000004_create_gc_batch_exports_table.php` | Batch exports |
| `Database/Migrations/2024_01_02_000005_add_journal_entry_to_gc_transactions.php` | GL linking |
| `Models/GiftCardTemplate.php` | Template model |
| `Models/GiftCardPrintHistory.php` | Print history model |
| `Models/GiftCardBatchExport.php` | Batch export model |
| `Services/GiftCardService.php` | Main business logic |
| `Services/GiftCardGeneratorService.php` | Secure code generation |
| `Services/GiftCardGLService.php` | Accounting integration |
| `Services/GiftCardExportService.php` | Export functionality |
| `Filament/Resources/GiftCardTemplateResource.php` | Template management |
| `Filament/Resources/GiftCardTemplateResource/Pages/*.php` | Template pages |
| `Filament/Pages/StaffGiftCardDashboard.php` | Staff dashboard |
| `resources/views/filament/pages/staff-dashboard.blade.php` | Dashboard view |
| `resources/views/pdf/gift-card.blade.php` | Card PDF template |

## Files to Modify

| File | Changes |
|------|---------|
| `Models/GiftCard.php` | Add template, staff fields, relationships |
| `Models/GiftCardTransaction.php` | Add journal_entry_id |
| `Filament/Resources/GiftCardResource.php` | Add template select, bulk actions |
| `Providers/GiftCardsServiceProvider.php` | Register services |
| `module.json` | Add permissions, sequences |
| `Lang/en/giftcards.php` | Add translations |
| `Lang/ar/giftcards.php` | Add Arabic translations |
| `modules/Accounting/Services/DefaultAccountsService.php` | Add gift card accounts |
| `modules/Accounting/Filament/Pages/DefaultAccountsPage.php` | Add gift card section |
| `modules/Accounting/Database/Seeders/ChartOfAccountsSeeder.php` | Add accounts |

---

## Verification Steps

1. [x] Create a gift card template with GL accounts
2. [x] Generate batch of 10 cards from template
3. [x] Verify cards have encrypted codes and hashes
4. [x] Assign 5 cards to a staff member
5. [x] Access staff dashboard, verify statistics
6. [ ] Sell a card → verify GL entry created (DR Cash, CR Liability)
7. [ ] Redeem card on invoice → verify GL entry (DR Liability, CR Revenue)
8. [x] Export cards to CSV/PDF
9. [x] Check print history recorded
10. [ ] Let a card expire → verify breakage GL entry
11. [x] Test Luhn validation on card numbers
12. [x] Verify all permissions work correctly

---

## Implementation Status

### Completed ✅
1. **Phase 1:** Gift Card Templates - DONE (simplified with single amount)
2. **Phase 2:** GL/Accounting Integration - DONE
3. **Phase 3:** Batch Generation & Security - DONE (Luhn algorithm)
4. **Phase 4:** Staff Assignment & Dashboard - DONE
5. **Phase 5:** Print & Export - DONE (PDF, CSV)
6. **Phase 6:** Update module.json - DONE

### Remaining Tasks
1. [x] Add gift card accounts to ChartOfAccountsSeeder - DONE (2220, 4550)
2. [ ] Multi-card payment in invoice (UI integration)
3. [x] Scheduled task for auto-expiring cards - DONE (`php artisan giftcards:expire`)

### GL Account Flow
- **Template accounts take priority** - falls back to system defaults
- Sale: DR Cash, CR Liability (from template)
- Redemption: DR Liability (from template), CR Service Revenue (default)
- Expiration: DR Liability (from template), CR Breakage Revenue (default)
- Discount: DR Expense (from template)
