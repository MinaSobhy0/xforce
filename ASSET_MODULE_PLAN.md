# Asset Management Module Implementation Plan

## Summary

Create an Asset Management module similar to Odoo's functionality with:
- **Asset Types**: Configuration for depreciation methods, useful life, and GL accounts
- **Assets**: Automatically created when products with asset type are purchased
- **Automatic Journal Entries**: Acquisition, monthly depreciation, and disposal entries
- **Depreciation Methods**: Straight-line, declining balance, sum-of-years digits

---

## Module Location

**New Module:** `/var/www/html/x_linic/modules/Assets/`

---

## Database Schema

### Table: `asset_types`

| Column | Type | Description |
|--------|------|-------------|
| id | UUID | Primary key |
| tenant_id | UUID | Multi-tenancy |
| code | string(50) | Auto-generated (AT-0001) |
| name | JSONB | Translatable name |
| description | JSONB | Translatable description |
| depreciation_method | string | straight_line, declining_balance, sum_of_years, no_depreciation |
| useful_life_years | integer | Years for depreciation |
| salvage_value_percent | decimal(5,2) | Residual value % |
| declining_balance_rate | decimal(5,2) | For declining balance method |
| fixed_asset_account_id | UUID | FK - DR on acquisition |
| accumulated_depreciation_account_id | UUID | FK - CR on depreciation |
| depreciation_expense_account_id | UUID | FK - DR on depreciation |
| gain_loss_account_id | UUID | FK - For disposal gain/loss |
| auto_create_on_purchase | boolean | Auto-create assets from PO |
| is_active | boolean | Active status |

### Table: `assets`

| Column | Type | Description |
|--------|------|-------------|
| id | UUID | Primary key |
| tenant_id | UUID | Multi-tenancy |
| code | string(50) | Auto-generated (AST-000001) |
| name | JSONB | Translatable name |
| asset_type_id | UUID | FK to asset_types |
| branch_id | UUID | FK to branches |
| acquisition_date | date | Purchase date |
| acquisition_cost_minor | integer | Original cost in cents |
| acquisition_method | string | purchase, transfer, donation, found |
| purchase_order_id | UUID | FK - Source PO |
| purchase_order_line_id | UUID | FK - Source PO line |
| product_id | UUID | FK - Source product |
| salvage_value_minor | integer | Calculated from type % |
| depreciable_value_minor | integer | Cost - salvage |
| accumulated_depreciation_minor | integer | Total depreciation to date |
| book_value_minor | integer | Cost - accumulated |
| depreciation_start_date | date | When depreciation starts |
| last_depreciation_date | date | Last entry date |
| status | string | draft, active, fully_depreciated, disposed, written_off |
| disposal_date | date | When disposed |
| disposal_value_minor | integer | Sale proceeds |
| disposal_method | string | sale, scrap, donation, theft, damage |
| serial_number | string | Asset serial |
| location | string | Physical location |
| assigned_to_user_id | UUID | FK - Assigned user |

### Table: `asset_depreciation_entries`

| Column | Type | Description |
|--------|------|-------------|
| id | UUID | Primary key |
| tenant_id | UUID | Multi-tenancy |
| asset_id | UUID | FK to assets |
| period_start | date | Month start |
| period_end | date | Month end |
| period_label | string | e.g., "2024-01" |
| depreciation_amount_minor | integer | This period's depreciation |
| accumulated_depreciation_minor | integer | Running total |
| book_value_minor | integer | Book value after entry |
| journal_entry_id | UUID | FK - Posted journal entry |
| status | string | draft, posted, reversed |

### Migration: `add_asset_type_id_to_products`

Add to `products` table:
- `asset_type_id` UUID nullable FK
- `is_asset` boolean default false

---

## Files to Create

### Models
| File | Purpose |
|------|---------|
| `Models/AssetType.php` | Asset type with depreciation config |
| `Models/Asset.php` | Asset with depreciation calculations |
| `Models/AssetDepreciationEntry.php` | Monthly depreciation records |

### Services
| File | Purpose |
|------|---------|
| `Services/AssetService.php` | Business logic (create, activate, depreciate, dispose) |
| `Services/AssetGLService.php` | Journal entries (acquisition, depreciation, disposal) |

### Filament Resources
| File | Purpose |
|------|---------|
| `Filament/Resources/AssetTypeResource.php` | Asset type CRUD |
| `Filament/Resources/AssetResource.php` | Asset CRUD with actions |
| `Filament/Resources/AssetResource/RelationManagers/DepreciationEntriesRelationManager.php` | View depreciation history |

### Event & Listener
| File | Purpose |
|------|---------|
| `modules/Inventory/Events/PurchaseOrderReceived.php` | Event when PO items received |
| `Listeners/CreateAssetOnPurchaseReceived.php` | Auto-create assets |

### Command
| File | Purpose |
|------|---------|
| `Console/Commands/ProcessDepreciationCommand.php` | Monthly depreciation scheduler |

### Providers
| File | Purpose |
|------|---------|
| `Providers/AssetsServiceProvider.php` | Main service provider |
| `Providers/EventServiceProvider.php` | Event listener registration |

### Translations
| File | Purpose |
|------|---------|
| `Lang/en/assets.php` | English translations |
| `Lang/ar/assets.php` | Arabic translations |

---

## Files to Modify

| File | Changes |
|------|---------|
| `modules/Inventory/Models/Product.php` | Add `asset_type_id`, `is_asset`, relationship |
| `modules/Inventory/Models/PurchaseOrderLine.php` | Dispatch `PurchaseOrderReceived` event |
| `modules/Accounting/Services/DefaultAccountsService.php` | Add asset account defaults |
| `app/Console/Kernel.php` | Schedule monthly depreciation command |

---

## Journal Entry Logic

### On Acquisition (when asset activated)
```
DR: Fixed Asset Account (from asset type)     [acquisition_cost]
CR: Accounts Payable / Cash                   [acquisition_cost]
```

### Monthly Depreciation
```
DR: Depreciation Expense Account              [monthly_amount]
CR: Accumulated Depreciation Account          [monthly_amount]
```

### On Disposal
```
DR: Accumulated Depreciation (remove)         [total_accumulated]
DR: Cash/Receivable (if proceeds)             [proceeds]
DR/CR: Gain/Loss on Disposal                  [difference]
CR: Fixed Asset Account (remove cost)         [acquisition_cost]
```

---

## Depreciation Methods

### 1. Straight Line
```php
annual = depreciable_value / useful_life_years
monthly = annual / 12
```

### 2. Declining Balance
```php
annual = book_value_at_year_start × rate%
// Don't depreciate below salvage value
```

### 3. Sum of Years Digits
```php
sum = n(n+1)/2  // where n = useful_life_years
annual = depreciable_value × (remaining_years / sum)
```

---

## Implementation Sequence

### Phase 1: Module Setup
1. Create module directory structure
2. Create `module.json`
3. Create migrations (4 files)
4. Create models (3 files)
5. Create service provider
6. Run migrations

### Phase 2: Services
1. Create `AssetGLService` (follows GiftCardGLService pattern)
2. Create `AssetService` (business logic)
3. Test journal entry creation

### Phase 3: Filament Resources
1. Create `AssetTypeResource` with form/table
2. Create `AssetResource` with:
   - Form for manual creation
   - Table with status badges
   - Activate action
   - Dispose action with form
3. Create `DepreciationEntriesRelationManager`

### Phase 4: Auto-Creation
1. Create `PurchaseOrderReceived` event
2. Modify `PurchaseOrderLine::receiveItems()` to dispatch event
3. Create `CreateAssetOnPurchaseReceived` listener
4. Register in EventServiceProvider
5. Test auto-creation flow

### Phase 5: Depreciation Command
1. Create `ProcessDepreciationCommand`
2. Add to Kernel schedule (monthly)
3. Test depreciation calculation
4. Verify journal entries

### Phase 6: Product Integration
1. Add asset fields to Product model
2. Update ProductResource form with asset type select
3. Test full flow: Product → PO → Receive → Asset created

---

## Verification Steps

1. **Setup**: Run `php artisan module:migrate Assets`
2. **Asset Types**: Create asset type "Computers" with 5-year straight-line
3. **Accounts**: Verify GL accounts are configured
4. **Product**: Create product "Laptop" marked as asset with type
5. **Purchase**: Create PO for the laptop
6. **Receive**: Mark PO as received
7. **Verify**: Check asset auto-created in draft status
8. **Activate**: Activate asset, verify acquisition journal entry
9. **Depreciate**: Run `php artisan assets:depreciate --month=2024-03`
10. **Verify**: Check depreciation entry and journal entry
11. **Dispose**: Dispose asset with sale proceeds
12. **Verify**: Check disposal journal entry with gain/loss

---

## Directory Structure

```
modules/Assets/
├── Config/
│   └── config.php
├── Console/Commands/
│   └── ProcessDepreciationCommand.php
├── Database/Migrations/
│   ├── 2024_03_01_000001_create_asset_types_table.php
│   ├── 2024_03_01_000002_create_assets_table.php
│   ├── 2024_03_01_000003_create_asset_depreciation_entries_table.php
│   └── 2024_03_01_000004_add_asset_type_id_to_products_table.php
├── Filament/Resources/
│   ├── AssetResource.php
│   ├── AssetResource/
│   │   ├── Pages/ (List, Create, Edit, View)
│   │   └── RelationManagers/DepreciationEntriesRelationManager.php
│   ├── AssetTypeResource.php
│   └── AssetTypeResource/Pages/ (List, Create, Edit)
├── Lang/
│   ├── ar/assets.php
│   └── en/assets.php
├── Listeners/
│   └── CreateAssetOnPurchaseReceived.php
├── Models/
│   ├── Asset.php
│   ├── AssetDepreciationEntry.php
│   └── AssetType.php
├── Providers/
│   ├── AssetsServiceProvider.php
│   └── EventServiceProvider.php
├── Services/
│   ├── AssetGLService.php
│   └── AssetService.php
└── module.json
```

---

## Key Patterns to Follow

| Pattern | Reference |
|---------|-----------|
| GL Service | `modules/GiftCards/Services/GiftCardGLService.php` |
| Event Listener | `modules/GiftCards/Listeners/ActivateGiftCardOnInvoicePaid.php` |
| Module Structure | `modules/GiftCards/module.json` |
| Depreciation Calc | `modules/Equipment/Models/Equipment.php` (basic version) |
| Account Integration | `modules/Accounting/Services/AccountingIntegrationService.php` |

---

## Notes

- All monetary values stored as `_minor` (cents) for precision
- Use `HasSequence` trait for auto-generated codes
- Use `HasTranslations` for bilingual name/description
- Pass `tenant_id` to `createJournalEntry()` (learned from gift card fix)
- Depreciation command supports `--dry-run` for testing
- Asset disposal calculates gain/loss automatically

---

## Implementation Checklist

### Phase 1: Module Setup
- [x] Create module directory `modules/Assets/`
- [x] Create `module.json` configuration file
- [x] Create `Config/config.php`
- [x] Create migration: `create_asset_types_table.php`
- [x] Create migration: `create_assets_table.php`
- [x] Create migration: `create_asset_depreciation_entries_table.php`
- [x] Create migration: `add_asset_type_id_to_products_table.php`
- [x] Create `Models/AssetType.php` with relationships and HasTranslations
- [x] Create `Models/Asset.php` with depreciation calculation methods
- [x] Create `Models/AssetDepreciationEntry.php`
- [x] Create `Providers/AssetsServiceProvider.php`
- [x] Register module in `modules_statuses.json`
- [x] Run migrations: `php artisan module:migrate Assets`

### Phase 2: Services
- [x] Create `Services/AssetGLService.php` with:
  - [x] `postAssetAcquisition()` method
  - [x] `postDepreciationEntry()` method
  - [x] `postAssetDisposal()` method (with gain/loss calculation)
- [x] Create `Services/AssetService.php` with:
  - [x] `createAsset()` method
  - [x] `activateAsset()` method
  - [x] `calculateDepreciation()` method (all 3 methods)
  - [x] `processMonthlyDepreciation()` method
  - [x] `disposeAsset()` method
- [ ] Update `DefaultAccountsService.php` with asset account methods

### Phase 3: Filament Resources
- [x] Create `AssetTypeResource.php` with form and table
- [x] Create `AssetTypeResource/Pages/ListAssetTypes.php`
- [x] Create `AssetTypeResource/Pages/CreateAssetType.php`
- [x] Create `AssetTypeResource/Pages/EditAssetType.php`
- [x] Create `AssetResource.php` with form, table, and status badges
- [x] Create `AssetResource/Pages/ListAssets.php`
- [x] Create `AssetResource/Pages/CreateAsset.php`
- [x] Create `AssetResource/Pages/EditAsset.php`
- [x] Create `AssetResource/Pages/ViewAsset.php`
- [x] Create `DepreciationEntriesRelationManager.php`
- [x] Add Activate action to AssetResource
- [x] Add Dispose action with form to AssetResource
- [ ] Add Write-Off action to AssetResource

### Phase 4: Auto-Creation from Purchase
- [x] Create `modules/Inventory/Events/PurchaseOrderReceived.php`
- [x] Modify `PurchaseOrderLine.php` to dispatch event on receive
- [x] Create `Listeners/CreateAssetOnPurchaseReceived.php`
- [x] Create `Providers/EventServiceProvider.php`
- [x] Register event listener

### Phase 5: Depreciation Command
- [x] Create `Console/Commands/ProcessDepreciationCommand.php`
- [x] Add `--month` option for specific period
- [x] Add `--dry-run` option for testing
- [x] Add to `routes/console.php` schedule (monthly on 1st at 03:00)

### Phase 6: Product Integration
- [x] Add `asset_type_id` and `is_asset` to Product model
- [x] Add `assetType()` relationship to Product
- [x] Update `ProductResource.php` form with asset type select

### Phase 7: Translations
- [x] Create `Lang/en/assets.php` with all labels
- [x] Create `Lang/ar/assets.php` with Arabic translations

### Phase 8: Testing & Verification
- [ ] Create asset type "Computers" with 5-year straight-line
- [ ] Verify GL accounts are configured correctly
- [ ] Create product "Laptop" marked as asset with type
- [ ] Create PO for the laptop product
- [ ] Mark PO as received
- [ ] Verify asset auto-created in draft status
- [ ] Activate asset, verify acquisition journal entry
- [ ] Run depreciation command for test month
- [ ] Verify depreciation entry and journal entry created
- [ ] Dispose asset with sale proceeds
- [ ] Verify disposal journal entry with gain/loss calculation
- [ ] Test declining balance depreciation method
- [ ] Test sum-of-years digits depreciation method
