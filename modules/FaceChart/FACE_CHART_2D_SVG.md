# 2D Face Chart - SVG-Based with Region Detection

## Overview

The 2D Face Chart now uses a **vector-based (SVG) anatomical face diagram** instead of a raster image. This provides:

- ✅ **Perfect quality** at any resolution (vector graphics)
- ✅ **Interactive region detection** - clickable facial zones
- ✅ **Auto-populated region field** - automatically fills region when clicking
- ✅ **Hover highlights** - regions light up on mouseover
- ✅ **Professional medical appearance** - anatomical accuracy

## Features

### Clickable Facial Regions

The face diagram is divided into **15 anatomical regions**:

1. **Forehead** - Frontalis muscle area
2. **Glabella** - Between the eyebrows
3. **Temples** (Left & Right) - Temporal regions
4. **Upper Eyelid** (Left & Right) - Above eyes
5. **Lower Eyelid** (Left & Right) - Below eyes
6. **Crow's Feet** (Left & Right) - Lateral to eyes
7. **Nose** - Nasal area
8. **Cheeks** (Left & Right) - Malar regions
9. **Nasolabial Folds** (Left & Right) - Smile lines
10. **Upper Lip** - Above the mouth
11. **Lower Lip** - Below the mouth
12. **Marionette Lines** (Left & Right) - Corner of mouth to chin
13. **Chin** - Mentalis area
14. **Jawline** (Left & Right) - Mandibular border
15. **Neck** - Platysma area

### How It Works

1. **Hover over any region** → It highlights with a blue glow
2. **Click on a region** → Automatically places a marker at that location
3. **Region auto-detected** → The "Region" field is pre-filled when creating the marker
4. **Manual placement** → Can also click anywhere for precise positioning

### Visual Elements

- **Face outline**: Skin-toned with anatomical proportions
- **Muscle lines**: Subtle reference lines showing muscle groups
- **Eyes**: Realistic with iris, pupil, and highlights
- **Interactive regions**: Translucent overlays with dashed borders
- **Hover effects**: Blue glow and solid borders on mouseover

## Drawing Tools

All the standard tools work on top of the SVG:

- 📍 **Marker Tool** - Click to place injection points
- ✏️ **Text Tool** - Add text annotations
- ➡️ **Arrow Tool** - Draw directional arrows (2 clicks)
- 🖊️ **Pen Tool** - Free-hand drawing (tablet/stylus optimized)
- ✨ **Select Tool** - Move and edit existing annotations

## Technical Details

### File Structure

```
modules/FaceChart/Resources/views/components/face-2d-svg.blade.php
```

The SVG is a Blade component with:
- Defined `<path>` elements for each region
- `data-region` attributes for identification
- Alpine.js click handlers that dispatch `region-clicked` events

### Canvas Layering

The UI uses a **2-layer approach**:

```
┌─────────────────────────────┐
│   Fabric.js Canvas          │ ← Transparent, annotations drawn here
│   (z-index: 10)             │
├─────────────────────────────┤
│   SVG Face Diagram          │ ← Background, clickable regions
│   (z-index: 1)              │
└─────────────────────────────┘
```

### Event Flow

1. User clicks on SVG region
2. SVG dispatches `region-clicked` event with region name
3. JavaScript calculates normalized coordinates (0-1)
4. Marker is created on Fabric.js canvas
5. Livewire saves to database with `region` field populated

### Database Storage

Markers are saved with:
```php
[
    'x' => 0.45,           // Normalized X coordinate (0-1)
    'y' => 0.32,           // Normalized Y coordinate (0-1)
    'z' => 0,              // Always 0 for 2D
    'view_type' => '2d',   // Distinguishes from 3D markers
    'region' => 'forehead' // Auto-detected from SVG click
]
```

## Customization

### Changing Colors

Edit `face-2d-svg.blade.php`:

```css
.face-region {
    fill: rgba(99, 102, 241, 0.15);      /* Region fill color */
    stroke: rgba(99, 102, 241, 0.8);     /* Border color */
}

.face-outline {
    fill: #f5d5c8;                       /* Skin tone */
    stroke: #d4a574;                     /* Outline color */
}
```

### Adding New Regions

1. Add SVG path element with `class="face-region"`
2. Add `data-region="new_region_name"` attribute
3. Add click handler: `@click="$dispatch('region-clicked', { region: 'new_region_name' })"`
4. Update database enum in migration if needed

### Adjusting Size

The SVG uses `viewBox="0 0 400 600"` for responsive scaling. Canvas size is determined by available space.

## Benefits Over Static Image

| Feature | Static Image (JPEG) | SVG Diagram |
|---------|--------------------|----|
| **Quality** | Pixelated when zoomed | Always crisp |
| **File size** | 77KB | ~15KB |
| **Region detection** | Manual coordinates | Automatic |
| **Customization** | Requires image editor | Edit in code |
| **Accessibility** | Image only | Clickable, semantic |
| **Medical accuracy** | Varies | Anatomically precise |

## Future Enhancements

Possible additions:
- [ ] Region tooltips showing names on hover
- [ ] Different view angles (profile, 3/4 view)
- [ ] Male vs female facial structure toggle
- [ ] Age-appropriate facial proportions
- [ ] Zoom and pan controls
- [ ] Export annotations as separate layer

## Browser Compatibility

Tested on:
- ✅ Chrome 90+
- ✅ Firefox 88+
- ✅ Safari 14+
- ✅ Edge 90+
- ✅ Mobile Safari (iOS 14+)
- ✅ Chrome Mobile (Android)

## Troubleshooting

**Q: Regions not clickable**
A: Check that `pointer-events: auto` is set on SVG container

**Q: Canvas behind SVG**
A: Ensure canvas wrapper has `z-index: 10` and `position: absolute`

**Q: Hover effects not working**
A: Verify `:hover` styles in SVG `<style>` section

**Q: Region clicks not detected**
A: Check browser console for `region-clicked` event logs
