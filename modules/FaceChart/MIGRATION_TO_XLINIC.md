# FaceChart Migration Guide — xforce → xlinic

This document describes how to port the FaceChart module (plus related changes in the Booking module) from **xforce** to **xlinic**.

---

## 1. Scope

What's being migrated:

- **FaceChart module** — full 2D/3D facial annotation editor (Livewire + Fabric.js).
- **SessionConsumable ↔ FaceChartMarker cascade** — wiring so deleting either side updates the other.
- **TreatmentSession integration** — embedding the 2D viewer/editor in the treatment session page.
- **Static assets** — face background images, marker/injection icons.
- **Language files** — EN + AR translations.

---

## 2. Files to copy

### 2.1 FaceChart module (copy the whole directory)

```
modules/FaceChart/
├── Config/
│   └── config.php
├── Database/
│   └── Migrations/
│       ├── 2024_04_05_000001_create_face_chart_markers_table.php
│       ├── 2024_04_05_000002_add_direction_to_face_chart_markers.php
│       ├── 2024_04_05_000003_add_arrow_endpoint_to_face_chart_markers.php
│       ├── 2024_04_05_000004_add_2d_view_support.php
│       └── 2024_04_17_000001_add_consumable_link_to_face_chart_markers.php
├── Lang/
│   ├── ar/face_chart.php
│   └── en/face_chart.php
├── Livewire/
│   ├── FaceChart2D.php            ← main editor (this session's work is here)
│   ├── FaceChart3D.php
│   └── FaceChartViewer.php        ← router/wrapper component
├── Models/
│   └── FaceChartMarker.php        ← contains consumable-cascade logic
├── Providers/
│   ├── FaceChartServiceProvider.php
│   └── RouteServiceProvider.php
├── Resources/views/
│   ├── components/face-2d-svg.blade.php
│   └── livewire/
│       ├── face-chart-2d.blade.php   ← most of the UX/Fabric.js lives here
│       ├── face-chart-3d.blade.php
│       └── face-chart-viewer.blade.php
├── Routes/
│   ├── api.php
│   └── web.php
├── Services/
│   └── FaceChartService.php
└── module.json
```

### 2.2 Booking module — edited files (merge, don't overwrite)

| File | Change |
|------|--------|
| `modules/Booking/Models/SessionConsumable.php` | Added `$skipMarkerCascade` flag + `deleting` event to remove linked markers. See §5.1. |
| `modules/Booking/Filament/Pages/TreatmentSession.php` | `removeConsumable()` uses model delete (triggers cascade) and dispatches `faceChartMarkersRefresh`. See §5.2. |
| `modules/Booking/Resources/views/filament/pages/treatment-session.blade.php` | Already embeds `@livewire('face_chart::face-chart-viewer', ...)` — keep as-is. |

### 2.3 Patients module — relationship

`modules/Patients/Models/Patient.php` needs:

```php
public function faceChartMarkers(): HasMany
{
    return $this->hasMany(\Modules\FaceChart\Models\FaceChartMarker::class);
}
```

### 2.4 Static assets

Copy from xforce `public/` to xlinic `public/`:

```
public/images/face_2d_front.jpg    ← default background (small, ~78 KB)
public/images/face_2d_front.png    ← higher-res (2.5 MB, optional)
public/images/face_2d_front.svg    ← vector (3.4 MB, optional)
public/images/face_2d_left.png     ← left profile view (optional)
public/images/face_2d_right.png    ← right profile view (optional)
public/images/injection-icon.png   ← REQUIRED — referenced by addMarker/renderMarker
public/images/markers/
├── filler.png
├── injection.png
├── laser.png
├── marking.png
└── thread.png
```

> The large PNG/SVG variants (>2 MB each) should ideally go through Git LFS, a CDN, or be downscaled. Only `face_2d_front.jpg` + `injection-icon.png` + the `markers/` folder are strictly required for the 2D editor to work.

---

## 3. Database migrations

After copying, run tenant migrations so every tenant gets the table:

```bash
php artisan tenant:migrate
```

Or for a single tenant during testing:

```bash
php artisan migrate --database=tenant --path=modules/FaceChart/Database/Migrations
```

Migration order (listed by timestamp — the `2024_04_17...` one is new from this work):

1. `create_face_chart_markers_table` — base table.
2. `add_direction_to_face_chart_markers` — direction_x/y/z, depth_mm.
3. `add_arrow_endpoint_to_face_chart_markers` — arrow_end_x/y/z for surface arrows.
4. `add_2d_view_support` — view_type column; changes coords to allow 0-1 fractions for 2D.
5. `add_consumable_link_to_face_chart_markers` — `session_consumable_id` FK + `product_id`.

### Verifying

```bash
php artisan tinker --execute="
DB::statement('SET search_path TO tenant_demo');
echo Schema::hasTable('face_chart_markers') ? 'ok' : 'missing';
"
```

---

## 4. Module registration

The module is auto-discovered if xlinic uses the same `ModuleManager` as xforce. Check:

- `module.json` is present with `active: true`.
- The service provider is listed under `providers`:
  `"Modules\\FaceChart\\Providers\\FaceChartServiceProvider"`
- Dependencies in `module.json`: `["Core", "Patients", "Booking"]` — make sure these modules exist in xlinic.

`FaceChartServiceProvider` registers:
- Livewire components (`face-chart-2d`, `face-chart-3d`, `face-chart-viewer`).
- Translations namespace `face_chart::`.
- Views namespace `face_chart::`.
- Config namespace `face_chart.*`.

---

## 5. Integration points

### 5.1 SessionConsumable cascade

`modules/Booking/Models/SessionConsumable.php` — add inside the class:

```php
/**
 * Flag to prevent circular cascade when a child FaceChartMarker
 * is the one initiating the delete.
 */
public static bool $skipMarkerCascade = false;

protected static function booted(): void
{
    parent::booted();

    static::saving(function (self $model) {
        $model->total_cost_minor = (int) ($model->quantity * $model->unit_cost_minor);
    });

    static::deleting(function (self $consumable) {
        if (self::$skipMarkerCascade) {
            return;
        }

        // Remove linked face chart markers without triggering consumable adjustment
        \Modules\FaceChart\Models\FaceChartMarker::$skipConsumableAdjustment = true;
        \Modules\FaceChart\Models\FaceChartMarker::where('session_consumable_id', $consumable->id)
            ->get()
            ->each(fn ($marker) => $marker->delete());
        \Modules\FaceChart\Models\FaceChartMarker::$skipConsumableAdjustment = false;
    });
}

public function faceChartMarkers(): HasMany
{
    return $this->hasMany(\Modules\FaceChart\Models\FaceChartMarker::class);
}
```

The matching cascade in `FaceChartMarker::booted()` (already part of the module copy) reduces `SessionConsumable.quantity` by the marker's `units` on delete, and deletes the consumable entirely if quantity hits 0.

### 5.2 TreatmentSession removeConsumable

Replace the body of `removeConsumable()`:

```php
public function removeConsumable(string $consumableId): void
{
    $consumable = SessionConsumable::find($consumableId);
    if ($consumable) {
        $consumable->delete(); // Triggers cascade to markers
    }

    $this->sessionConsumables = array_values(
        array_filter($this->sessionConsumables, fn ($c) => (string) $c['id'] !== $consumableId)
    );

    $this->dispatch('faceChartMarkersRefresh');

    Notification::make()
        ->title(__('booking::session.messages.consumable_removed'))
        ->success()
        ->send();
}
```

### 5.3 TreatmentSession refresh listener

Already present as `#[On('sessionConsumablesRefresh')] public function reloadSessionConsumables()` — ensure it exists. The FaceChart2D component dispatches this event when markers change so the consumables list rebuilds its `markers_count` column.

### 5.4 Mounting the editor in the treatment session blade

```blade
@livewire('face_chart::face-chart-viewer', [
    'patientId'     => $patient->id,
    'appointmentId' => $appointment?->id,
    'editMode'      => !$this->isViewMode(),
], key('face-chart-' . $patient->id))
```

---

## 6. External dependencies

The 2D editor loads **Fabric.js 5.3.0** from a CDN inside `face-chart-2d.blade.php`:

```html
<script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.0/fabric.min.js"></script>
```

If xlinic runs in an offline/air-gapped environment, self-host `fabric.min.js` and update the path.

No npm dependencies are required.

---

## 7. Feature checklist (what you should be able to do after migration)

- [ ] Open a patient's treatment session page → FaceChart section loads.
- [ ] Pick the **marker** tool → tap the face → wizard opens with consumable list filtered by stock.
- [ ] Pick the **text** tool → tap → placeholder "Click to edit" fades in, clears on typing, saves only if non-empty.
- [ ] Pick the **text** tool → tap an existing text → cursor lands at tap position, not "select-all".
- [ ] Pick the **arrow** tool → drag → arrow persists on reload.
- [ ] Pick the **pen** tool → draw → free-hand path persists on reload.
- [ ] Pick the **eraser** tool → tap any current-session annotation → it's removed.
- [ ] Markers list shows **injection points only** (no arrows/text/drawings).
- [ ] Previous-session annotations are **non-editable** — grayed out, locked, shown with amber "Previous Session · <date>" badge in the list.
- [ ] Deleting a `SessionConsumable` removes its linked markers.
- [ ] Deleting a marker reduces `SessionConsumable.quantity` by `marker.units`; consumable is deleted if quantity hits 0.
- [ ] Click a row in the markers list → pulsing gold highlight appears on the matching point on the canvas.
- [ ] Default tool on load is **none** (no tool highlighted); clicking the canvas does nothing until a tool is picked.

---

## 8. Configuration

`modules/FaceChart/Config/config.php` exposes:

- `face_2d_image_path` — default JPG background.
- `face_2d_images.{front,left,right}` — per-view images for the 3-view 2D editor.
- `marker_colors` — per-marker-type default colors.
- `face_regions` — localized region labels.
- `marker_types`, `unit_types` — for dropdowns.

Override per-tenant via the settings table if xlinic supports it (the `module.json` declares `settings.face_chart.*`).

---

## 9. Permissions

`module.json` declares these permissions (copy `default_role_permissions` into your seeder if xlinic uses one):

```
face_chart.view_any, face_chart.view, face_chart.create, face_chart.update, face_chart.delete
```

Hook into xlinic's permission system the same way other modules do (e.g., via `ChecksResourcePermissions` trait, `PermissionServiceProvider`, or whatever xlinic uses).

---

## 10. Testing after migration

Quick smoke test:

```bash
# 1. Run migrations for a tenant
php artisan migrate --database=tenant --path=modules/FaceChart/Database/Migrations

# 2. Confirm table exists and has expected columns
php artisan tinker --execute="
DB::statement('SET search_path TO tenant_demo');
print_r(Schema::getColumnListing('face_chart_markers'));
"

# 3. Clear caches after copying views/translations
php artisan view:clear && php artisan cache:clear && php artisan config:clear
```

Then:
1. Log in as a tenant user with `face_chart.*` permissions.
2. Open an appointment → treatment session page.
3. Scroll to the FaceChart section and run through the checklist in §7.

---

## 11. Known gotchas

- **Canvas height**: the Livewire component renders into a parent with `min-height: 700px` — make sure the wrapper in the treatment session blade preserves this height.
- **Large image assets**: shipping 3.4 MB SVG / 2.5 MB PNGs through normal git bloats the repo — consider Git LFS or a CDN path in `face_chart.face_2d_image_path`.
- **PgBouncer `transaction` pool mode** breaks `SET search_path` persistence — the multi-tenant schema switch relies on `session` pool mode.
- **Livewire key collision**: the viewer mount uses `key('face-chart-' . $patient->id)` — if xlinic has a different Livewire key convention, align it.
- **Alpine.js version**: the component uses Alpine 3 syntax (`x-data`, `@click.stop`). If xlinic is still on Alpine 2, patches are needed.

---

## 12. Migration checklist (quick)

- [ ] Copy `modules/FaceChart/` wholesale.
- [ ] Apply the SessionConsumable cascade block (§5.1).
- [ ] Update `TreatmentSession::removeConsumable` (§5.2).
- [ ] Add `faceChartMarkers()` relation to `Patient` model.
- [ ] Copy `public/images/injection-icon.png` and `public/images/markers/*.png`.
- [ ] Copy at least one `public/images/face_2d_front.*` background.
- [ ] Run tenant migrations.
- [ ] Clear view/config/cache.
- [ ] Seed permissions for the `face_chart.*` set.
- [ ] Smoke test the §7 checklist.

---

## 13. Reference commits (xforce)

| Commit | Summary |
|--------|---------|
| `595b98fe` | text edit UX, free-hand save, mobile touch, default no-tool |
| `a7f6ec57` | link markers to consumables with cascade and read-only previous sessions |
| `76f8b777` | improve arrow drawing and add marker persistence |
| `6b81436e` | enhance 2D annotation editor with drawing tools |
| `d4335d47` | suppress native click event after drag is detected |
| `7a873795` | prevent add-point popup when dragging to rotate model |
| `2769cc21` | enhance pointer interaction for arrow drawing |
| `44527d3d` | ensure surface-following arrows render above face model |
| `8b60c32d` | arrows perpendicular to face surface (normal direction) |

Reviewing these commits in order gives the clearest picture of the FaceChart evolution if you need to cherry-pick rather than bulk-copy.
