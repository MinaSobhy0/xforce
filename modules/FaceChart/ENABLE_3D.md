# Re-enabling 3D Face Chart

The 3D face chart has been temporarily disabled but all code remains intact.

## Current State

- ❌ 3D tab hidden
- ✅ 2D view is the default and only visible view
- ✅ All 3D code preserved and functional

## To Re-enable 3D View

Edit: `modules/FaceChart/Resources/views/livewire/face-chart-viewer.blade.php`

### Step 1: Uncomment the 3D Tab Button

Find this section (around line 5):
```blade
{{-- 3D Tab - TEMPORARILY DISABLED --}}
{{--
<button
    @click="activeTab = '3d'"
    ...
</button>
--}}
```

Remove the `{{--` and `--}}` comment markers to uncomment it.

### Step 2: Uncomment the 3D View Content

Find this section (around line 33):
```blade
{{-- 3D View - TEMPORARILY DISABLED --}}
{{--
<div x-show="activeTab === '3d'" x-cloak>
    @livewire('face_chart::face-chart-3d', [
        ...
    ])
</div>
--}}
```

Remove the `{{--` and `--}}` comment markers.

### Step 3: (Optional) Change Default Tab

If you want 3D to be the default tab, change line 1:
```blade
<div x-data="{ activeTab: '2d' }" class="space-y-4">
```

To:
```blade
<div x-data="{ activeTab: '3d' }" class="space-y-4">
```

### Step 4: Clear Cache

```bash
php artisan view:clear
```

## Quick Enable Script

Run this to automatically uncomment both sections:

```bash
cd /var/www/html/xforce
sed -i 's/{{-- 3D Tab - TEMPORARILY DISABLED --}}//' modules/FaceChart/Resources/views/livewire/face-chart-viewer.blade.php
sed -i 's/{{-- 3D View - TEMPORARILY DISABLED --}}//' modules/FaceChart/Resources/views/livewire/face-chart-viewer.blade.php
sed -i '/3D Tab/,/--}}/s/{{--//' modules/FaceChart/Resources/views/livewire/face-chart-viewer.blade.php
sed -i '/3D Tab/,/--}}/s/--}}//' modules/FaceChart/Resources/views/livewire/face-chart-viewer.blade.php
sed -i '/3D View/,/--}}/s/{{--//' modules/FaceChart/Resources/views/livewire/face-chart-viewer.blade.php
sed -i '/3D View/,/--}}/s/--}}//' modules/FaceChart/Resources/views/livewire/face-chart-viewer.blade.php
php artisan view:clear
```

## What Was Disabled

- 3D tab button in the navigation
- 3D view content section
- Tab switching between 3D and 2D

## What Still Works

- All 3D Livewire component code (`FaceChart3D.php`)
- All 3D view templates
- 3D model loading (`face_head.glb`)
- 3D marker rendering
- Three.js integration

The 3D functionality is fully preserved and can be instantly re-enabled by uncommenting the blade template sections.
