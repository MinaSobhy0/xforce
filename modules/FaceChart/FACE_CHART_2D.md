# 2D Face Chart Annotation - Implementation Guide

## Overview

The 2D Face Chart Annotation feature has been successfully implemented alongside the existing 3D view. Doctors can now:

- ✅ Switch between 3D and 2D views via tabs
- ✅ Mark injection points on a 2D face image
- ✅ Add text annotations with custom styling
- ✅ Draw arrows to indicate direction
- ✅ View and filter historical markers
- ✅ All annotations persist to the database

---

## What Was Implemented

### 1. Database Changes
**Migration:** `modules/FaceChart/Database/Migrations/2024_04_05_000004_add_2d_view_support.php`

Added columns to `face_chart_markers` table:
- `view_type` (enum: '3d', '2d') - Distinguishes between 3D and 2D markers
- `annotation_text` (text, nullable) - Stores free text annotations
- `annotation_style` (jsonb, nullable) - Stores text styling (font, size, color, bold, italic)
- Index on `(patient_id, view_type)` for efficient queries

### 2. New Components

#### FaceChartViewer (Parent Wrapper)
**File:** `modules/FaceChart/Livewire/FaceChartViewer.php`

Parent component that provides tab switching between 3D and 2D views.

#### FaceChart2D (2D Component)
**File:** `modules/FaceChart/Livewire/FaceChart2D.php`

Livewire component handling:
- Canvas interactions
- Marker creation/update/deletion
- Text annotation management
- Filters (date range, region, marker type)
- Statistics display

#### View Template
**File:** `modules/FaceChart/Resources/views/livewire/face-chart-2d.blade.php`

Full-featured 2D canvas with:
- Fabric.js integration for rich annotations
- Toolbar with tools: marker, text, arrow, select, clear
- Filters sidebar
- Marker list
- Statistics panel

### 3. Updated Files

- **Model:** `modules/FaceChart/Models/FaceChartMarker.php`
  - Added `view_type`, `annotation_text`, `annotation_style` to fillable
  - Added `annotation_style` to casts
  - Added `scopeForView()` method
  - Updated `toMarkerData()` to include view type and annotations

- **Service:** `modules/FaceChart/Services/FaceChartService.php`
  - Updated `getMarkersForVisualization()` to support marker type filter

- **Integration:** `modules/Booking/Resources/views/filament/pages/treatment-session.blade.php`
  - Changed from `face-chart-3d` to `face-chart-viewer` component

- **Config:** `modules/FaceChart/Config/config.php`
  - Added 2D image path configuration
  - Added canvas size limits

- **Translations:**
  - `modules/FaceChart/Lang/en/face_chart.php` - English translations
  - `modules/FaceChart/Lang/ar/face_chart.php` - Arabic translations

---

## Current Background Image

The system uses a professional facial anatomy illustration showing all major facial muscle groups:

- **File:** `public/images/face_2d_front.jpg`
- **Format:** JPEG (Progressive)
- **Dimensions:** 1024x1024px (1:1 aspect ratio)
- **Size:** 77KB
- **Content:** Detailed anatomical illustration with muscle structure
- **Background:** Dark blue for optimal contrast

### How to Replace with a Different Image

If you want to use a different background image:

#### Option 1: Using Environment Variable

1. Place your 2D face image in `public/images/` (e.g., `face_custom.jpg`)
2. Add to your `.env` file:
   ```
   FACE_CHART_2D_IMAGE=/images/face_custom.jpg
   ```
3. Clear config cache:
   ```bash
   php artisan config:clear
   ```

#### Option 2: Direct Configuration

1. Place your image in `public/images/`
2. Edit `modules/FaceChart/Config/config.php`:
   ```php
   'face_2d_image_path' => '/images/your_face_image.jpg',
   ```

### Recommended Image Specifications

- **Format:** JPG, PNG, or SVG
- **Size:** 800x1200px or larger (higher resolution is better)
- **Aspect Ratio:** 1:1 (square) or 2:3 (portrait)
- **Quality:** High resolution for detailed annotations
- **Content:** Front-facing face view with clear anatomical landmarks
- **Background:** Neutral or contrasting color for marker visibility

---

## Usage Guide

### For End Users (Doctors)

1. **Navigate to Treatment Session**
   - Open a patient record
   - Start or view a treatment session
   - Scroll to "Face Chart" section

2. **Switch to 2D View**
   - Click the "2D Annotation" tab at the top

3. **Add Injection Markers**
   - Click the marker tool (pin icon) in the toolbar
   - Click on the face image where you want to add a marker
   - Fill in the marker details (product, units, region, etc.)

4. **Add Text Annotations**
   - Click the text tool (pencil icon)
   - Click where you want to add text
   - Type your annotation
   - Click outside to save

5. **Draw Arrows**
   - Click the arrow tool
   - Click to start the arrow
   - Drag to set direction

6. **Move/Edit Annotations**
   - Click the select tool (cursor icon)
   - Click on any annotation to select it
   - Drag to move
   - Double-click text to edit

7. **Filter Historical Data**
   - Use the filters sidebar to:
     - Filter by date range
     - Filter by face region
     - Filter by marker type
   - Click "Apply Filters"

8. **View Statistics**
   - See total markers count
   - See total units administered
   - See appointment count

### For Administrators

#### Customizing Colors

Edit `modules/FaceChart/Config/config.php`:

```php
'marker_colors' => [
    'injection' => '#3B82F6',      // Blue - Botox
    'filler_point' => '#EF4444',   // Red - Filler
    'laser_spot' => '#F59E0B',     // Amber - Laser
    'thread_anchor' => '#8B5CF6',  // Purple - Thread
    'marking' => '#10B981',        // Green - General marking
],
```

#### Adding New Regions

1. Add to `FaceChartMarker::REGIONS` constant
2. Add translation in language files
3. Update config if using config-based regions

---

## Technical Details

### Data Model

**2D Marker Conventions:**
- `view_type = '2d'` - Identifies as 2D marker
- `z = 0` - Indicates flat 2D surface
- `x, y` - Normalized 0-1 coordinates on canvas
- `direction_x, direction_y` - 2D arrow direction (if applicable)
- `direction_z, depth_mm, arrow_end_z` - NULL for 2D
- `annotation_text` - Free text content for text annotations
- `annotation_style` - JSONB with font settings:
  ```json
  {
    "fontSize": 16,
    "fontFamily": "Arial",
    "textColor": "#000000",
    "bold": false,
    "italic": false
  }
  ```

### Technology Stack

- **Frontend:** Livewire 3 + Alpine.js
- **Canvas Library:** Fabric.js 5.3.0 (loaded via CDN)
- **Styling:** Tailwind CSS
- **Backend:** Laravel 11
- **Database:** PostgreSQL with JSONB support

### Canvas Coordinates

Coordinates are stored as normalized values (0-1 range):
- When saving: `normalizedX = clickX / canvas.width`
- When rendering: `canvasX = marker.x * canvas.width`

This ensures markers remain correctly positioned regardless of canvas size.

### Event Flow

1. User clicks canvas → Alpine.js captures click
2. Alpine dispatches Livewire event with coordinates
3. Livewire creates marker via FaceChartService
4. Database saves marker with `view_type='2d'`
5. Livewire reloads markers and dispatches to frontend
6. Fabric.js renders markers on canvas

---

## API Reference

### Livewire Events

**From Frontend to Backend:**
- `canvasClick(data)` - User clicked on canvas to add marker
- `markerUpdate(markerId, data)` - User modified existing marker
- `markerDelete(markerId)` - User deleted marker
- `textAnnotationSave(data)` - User saved text annotation

**From Backend to Frontend:**
- `markersLoaded(markers)` - Markers loaded, render on canvas

### JavaScript Methods (Alpine.js)

```javascript
// Initialize canvas
init()

// Load background image
loadBackgroundImage(imagePath)

// Handle canvas click
handleCanvasClick(pointer)

// Add text annotation
addTextAnnotation(x, y)

// Add arrow
addArrow(x, y)

// Render markers
renderMarkers()

// Render individual marker point
renderMarkerPoint(marker)

// Render text annotation
renderTextAnnotation(marker)
```

---

## Troubleshooting

### Markers not appearing
1. Check browser console for JavaScript errors
2. Verify Fabric.js loaded correctly (check Network tab)
3. Ensure `view_type='2d'` in database
4. Check if filters are excluding markers

### Background image not loading
1. Verify image exists at path specified in config
2. Check file permissions (should be readable by web server)
3. Check browser console for 404 errors
4. Try using absolute URL: `https://yoursite.com/images/face.jpg`

### Text annotations not saving
1. Check if appointment_id is set (required for edit mode)
2. Verify user has permission to edit
3. Check browser console for Livewire errors
4. Ensure `annotation_text` column exists in database

### Canvas too small/large
1. Adjust in config:
   ```php
   'face_2d_canvas_max_width' => 1200,
   'face_2d_canvas_max_height' => 1600,
   ```
2. Canvas auto-sizes to container, respecting these limits

---

## Future Enhancements

Potential features for future development:

- [ ] Multi-view support (front, side, top views)
- [ ] Image upload directly from UI
- [ ] Color picker for markers
- [ ] Marker grouping/layers
- [ ] Export annotated image as PDF
- [ ] Annotation templates/presets
- [ ] Freehand drawing tool
- [ ] Zoom/pan controls
- [ ] Undo/redo functionality
- [ ] Real-time collaboration (multiple doctors annotating simultaneously)

---

## Testing Checklist

- [x] Database migration runs successfully
- [x] Tab switching works (3D ↔ 2D)
- [x] Can add markers in 2D view
- [x] Can add text annotations
- [x] Can draw arrows
- [x] Markers persist to database
- [x] Filters work (date, region, type)
- [x] Statistics display correctly
- [x] Edit permissions enforced
- [x] Historical markers visible but not editable
- [x] Data isolation (2D markers don't appear in 3D view)
- [x] Translations work (English and Arabic)
- [x] Responsive layout works on desktop

---

## Support

For issues or questions:
1. Check this documentation first
2. Review browser console for errors
3. Check Laravel logs: `storage/logs/laravel.log`
4. Verify database schema matches migration
5. Contact development team

---

**Version:** 1.0.0
**Last Updated:** 2026-04-05
**Author:** Claude Code Implementation
