# AMR (Ambulatory Medical Records) Profile Implementation Plan

## Overview

This document outlines the implementation plan for the AMR Profile system - a comprehensive medical profile for patients that includes allergies, medications, contraindications, medical history, skin assessments, and lifestyle information.

---

## Current Status: COMPLETE

All AMR Profile features have been implemented and are ready for testing.

---

## Phase 1: Database Schema COMPLETE

### 1.1 Medical Profiles Table
**File:** `modules/Patients/Database/Migrations/2024_01_20_000001_create_medical_profiles_table.php`

- [x] Create migration file
- [x] Add patient_id (unique FK)
- [x] Add blood_type, is_pregnant, is_breastfeeding
- [x] Add fitzpatrick_type for skin classification
- [x] Add insurance_info (JSONB)
- [x] Add status (active, archived, transferred)
- [x] Add review tracking (last_reviewed_at, reviewed_by)
- [x] Migration executed on all tenants

### 1.2 Medical Allergies Table
**File:** `modules/Patients/Database/Migrations/2024_01_20_000002_create_medical_allergies_table.php`

- [x] Create migration file
- [x] Add allergy_type (drug, food, environmental, topical, metal, latex, other)
- [x] Add allergen, severity (mild, moderate, severe, life_threatening)
- [x] Add reaction, discovered_date, is_confirmed
- [x] Add show_alert flag
- [x] Migration executed on all tenants

### 1.3 Medical Medications Table
**File:** `modules/Patients/Database/Migrations/2024_01_20_000003_create_medical_medications_table.php`

- [x] Create migration file
- [x] Add medication_name, generic_name, dosage, frequency, route
- [x] Add reason, start_date, end_date, is_ongoing
- [x] Add affects_treatment, treatment_implications
- [x] Add prescribing_doctor, is_otc
- [x] Migration executed on all tenants

### 1.4 Medical Contraindications Table
**File:** `modules/Patients/Database/Migrations/2024_01_20_000004_create_medical_contraindications_table.php`

- [x] Create migration file
- [x] Add contraindication_type (absolute, relative, temporary)
- [x] Add name, description, affected_services (JSONB)
- [x] Add start_date, end_date, is_active
- [x] Add source, identified_by, identified_at
- [x] Add show_booking_alert, block_booking
- [x] Migration executed on all tenants

### 1.5 Medical Histories Table
**File:** `modules/Patients/Database/Migrations/2024_01_20_000005_create_medical_histories_table.php`

- [x] Create migration file
- [x] Add history_type (medical_condition, surgery, hospitalization, family_history, social_history)
- [x] Add name, description, onset_date, resolved_date
- [x] Add is_ongoing, severity, family_relationship
- [x] Add affects_treatment, treatment_implications
- [x] Add verification fields
- [x] Migration executed on all tenants

### 1.6 Skin Assessments Table
**File:** `modules/Patients/Database/Migrations/2024_01_20_000006_create_skin_assessments_table.php`

- [x] Create migration file
- [x] Add fitzpatrick_type assessment
- [x] Add skin characteristics (oily type, sensitivity, texture, pore size, tone)
- [x] Add measurements (hydration, elasticity, pigmentation, acne severity)
- [x] Add conditions (current, previous, aging signs, sun damage)
- [x] Add assessment fields (areas of concern, goals, observations, recommendations)
- [x] Add assessment_photos (JSONB)
- [x] Migration executed on all tenants

### 1.7 Lifestyle Info Table
**File:** `modules/Patients/Database/Migrations/2024_01_20_000007_create_lifestyle_info_table.php`

- [x] Create migration file
- [x] Add smoking info (status, frequency, years, quit_date)
- [x] Add alcohol info (status, frequency)
- [x] Add exercise info (level, details)
- [x] Add sun exposure info (level, sunscreen use, tanning beds)
- [x] Add sleep info (hours, issues)
- [x] Add diet info (type, restrictions)
- [x] Add occupational exposures
- [x] Migration executed on all tenants

---

## Phase 2: Models COMPLETE

### 2.1 MedicalProfile Model
**File:** `modules/Patients/Models/MedicalProfile.php`

- [x] Create model with HasTenancy, SoftDeletes traits
- [x] Define fillable attributes and casts
- [x] Add BLOOD_TYPES and FITZPATRICK_TYPES constants
- [x] Add patient(), reviewedByUser(), createdByUser(), updatedByUser() relationships
- [x] Add allergies(), medications(), contraindications(), medicalHistories() relationships
- [x] Add skinAssessments(), lifestyleInfo() relationships
- [x] Add helper methods (hasCriticalAllergies, getActiveContraindications, etc.)
- [x] Add scopes (active, needsReview)
- [x] Add getOrCreateForPatient() static method

### 2.2 MedicalAllergy Model
**File:** `modules/Patients/Models/MedicalAllergy.php`

- [x] Create model with constants for types and severities
- [x] Add medicalProfile() relationship
- [x] Add scopes (ofType, critical, lifeThreatening, withAlerts, confirmed)
- [x] Add helper methods (isCritical, isLifeThreatening)

### 2.3 MedicalMedication Model
**File:** `modules/Patients/Models/MedicalMedication.php`

- [x] Create model with constants for routes and frequencies
- [x] Add medicalProfile() relationship
- [x] Add scopes (ongoing, discontinued, affectsTreatment, prescription, overTheCounter)
- [x] Add discontinue() method

### 2.4 MedicalContraindication Model
**File:** `modules/Patients/Models/MedicalContraindication.php`

- [x] Create model with constants for types and sources
- [x] Add medicalProfile() and identifiedByUser() relationships
- [x] Add scopes (active, absolute, relative, temporary, blocking, withAlerts, forService)
- [x] Add helper methods (isAbsolute, isTemporary, hasExpired, affectsService, deactivate, reactivate)

### 2.5 MedicalHistory Model
**File:** `modules/Patients/Models/MedicalHistory.php`

- [x] Create model with constants for history types and severities
- [x] Add medicalProfile() and verifiedByUser() relationships
- [x] Add scopes (ofType, medicalConditions, surgeries, familyHistory, ongoing, resolved, affectsTreatment, verified)
- [x] Add markAsVerified() method

### 2.6 SkinAssessment Model
**File:** `modules/Patients/Models/SkinAssessment.php`

- [x] Create model with constants for all assessment options
- [x] Add medicalProfile(), appointment(), assessedByUser() relationships
- [x] Add helper methods (isHighSensitivity, hasAgingSigns, hasSunDamage, getSummary, getTreatmentConsiderations)

### 2.7 LifestyleInfo Model
**File:** `modules/Patients/Models/LifestyleInfo.php`

- [x] Create model with constants for all lifestyle options
- [x] Add medicalProfile() relationship
- [x] Add helper methods (isSmoker, isFormerSmoker, hasHighSunExposure, usesTanningBeds, getTreatmentRiskFactors)

### 2.8 Patient Model Updates
**File:** `modules/Patients/Models/Patient.php`

- [x] Add medicalProfile() HasOne relationship
- [x] Add hasMedicalProfile() method
- [x] Add getOrCreateMedicalProfile() method

---

## Phase 3: Filament UI - Medical Profile Page COMPLETE

### 3.1 MedicalProfilePage
**File:** `modules/Patients/Filament/Pages/MedicalProfilePage.php`

- [x] Create Filament Page with tabs
- [x] Implement Overview tab with basic info and stats
- [x] Implement Allergies tab with add/delete functionality
- [x] Implement Medications tab with add/delete functionality
- [x] Implement Contraindications tab with add/delete functionality
- [x] Implement Medical History tab with add/delete functionality
- [x] Implement Skin Assessment tab (view latest)
- [x] Implement Lifestyle tab with save functionality
- [x] Add critical alerts section
- [x] Add mark as reviewed action
- [x] Add back to patient action

### 3.2 Medical Profile Blade View
**File:** `modules/Patients/Resources/views/filament/pages/medical-profile.blade.php`

- [x] Create tabbed interface
- [x] Implement patient info bar with alert indicators
- [x] Implement all tab content sections
- [x] Add forms for adding allergies, medications, contraindications, history
- [x] Add delete functionality with confirmation
- [x] Style with consistent color coding (red for allergies, blue for medications, etc.)

---

## Phase 4: View Patient Integration COMPLETE

### 4.1 ViewPatient Page Updates
**File:** `modules/Patients/Filament/Resources/PatientResource/Pages/ViewPatient.php`

- [x] Add Medical Profile action button in header
- [x] Link to medical profile page with patient_id parameter

---

## Phase 5: TreatmentSession Integration COMPLETE

### 5.1 TreatmentSession.php Updates
**File:** `modules/Booking/Filament/Pages/TreatmentSession.php`

- [x] Add medicalProfile property
- [x] Load patient.medicalProfile in eager loading
- [x] Add hasMedicalProfile() method
- [x] Add hasMedicalAlerts() method
- [x] Remove old AMR test-based methods

### 5.2 Treatment Session Blade View Updates
**File:** `modules/Booking/Resources/views/filament/pages/treatment-session.blade.php`

- [x] Remove old AMR test-based alert card
- [x] Keep allergies and contraindications alerts (from medicalHistory)

---

## Phase 6: Translations COMPLETE

### 6.1 English Medical Profile Translations
**File:** `modules/Patients/Lang/en/medical_profile.php`

- [x] Add all tab labels
- [x] Add all section labels
- [x] Add all field labels
- [x] Add all action labels
- [x] Add all message labels

### 6.2 Arabic Medical Profile Translations
**File:** `modules/Patients/Lang/ar/medical_profile.php`

- [x] Add all Arabic translations

### 6.3 Patient Translations Update
**Files:** `modules/Patients/Lang/en/patients.php`, `modules/Patients/Lang/ar/patients.php`

- [x] Add medical_profile action label

### 6.4 Session Translations Cleanup
**Files:** `modules/Booking/Lang/en/session.php`, `modules/Booking/Lang/ar/session.php`

- [x] Remove old AMR test-based translations

---

## Files Created

### Migrations
- [x] `modules/Patients/Database/Migrations/2024_01_20_000001_create_medical_profiles_table.php`
- [x] `modules/Patients/Database/Migrations/2024_01_20_000002_create_medical_allergies_table.php`
- [x] `modules/Patients/Database/Migrations/2024_01_20_000003_create_medical_medications_table.php`
- [x] `modules/Patients/Database/Migrations/2024_01_20_000004_create_medical_contraindications_table.php`
- [x] `modules/Patients/Database/Migrations/2024_01_20_000005_create_medical_histories_table.php`
- [x] `modules/Patients/Database/Migrations/2024_01_20_000006_create_skin_assessments_table.php`
- [x] `modules/Patients/Database/Migrations/2024_01_20_000007_create_lifestyle_info_table.php`

### Models
- [x] `modules/Patients/Models/MedicalProfile.php`
- [x] `modules/Patients/Models/MedicalAllergy.php`
- [x] `modules/Patients/Models/MedicalMedication.php`
- [x] `modules/Patients/Models/MedicalContraindication.php`
- [x] `modules/Patients/Models/MedicalHistory.php`
- [x] `modules/Patients/Models/SkinAssessment.php`
- [x] `modules/Patients/Models/LifestyleInfo.php`

### Filament Pages
- [x] `modules/Patients/Filament/Pages/MedicalProfilePage.php`

### Blade Views
- [x] `modules/Patients/Resources/views/filament/pages/medical-profile.blade.php`

### Translations
- [x] `modules/Patients/Lang/en/medical_profile.php`
- [x] `modules/Patients/Lang/ar/medical_profile.php`

---

## Files Modified

### Models
- [x] `modules/Patients/Models/Patient.php` - Add medicalProfile relationship

### Filament Resources
- [x] `modules/Patients/Filament/Resources/PatientResource/Pages/ViewPatient.php` - Add Medical Profile action

### Treatment Session
- [x] `modules/Booking/Filament/Pages/TreatmentSession.php` - Load medicalProfile
- [x] `modules/Booking/Resources/views/filament/pages/treatment-session.blade.php` - Cleanup

### Translations
- [x] `modules/Patients/Lang/en/patients.php` - Add action label
- [x] `modules/Patients/Lang/ar/patients.php` - Add action label
- [x] `modules/Booking/Lang/en/session.php` - Cleanup
- [x] `modules/Booking/Lang/ar/session.php` - Cleanup

---

## Verification Checklist

### Functionality Tests
- [ ] Create new medical profile for patient
- [ ] Add allergy with different severities
- [ ] Add medication with treatment implications
- [ ] Add contraindication that blocks booking
- [ ] Add medical history entry
- [ ] View skin assessment (if available)
- [ ] Save lifestyle information
- [ ] Mark profile as reviewed
- [ ] Verify critical alerts display correctly

### UI Tests
- [ ] Medical Profile button appears in View Patient
- [ ] All tabs navigate correctly
- [ ] Forms validate required fields
- [ ] Delete confirmations work
- [ ] RTL layout works for Arabic

### Integration Tests
- [ ] Treatment Session loads patient medical profile
- [ ] Allergies alert card displays for patients with allergies
- [ ] Contraindications alert card displays correctly

### Multi-tenancy Tests
- [ ] Medical profile data isolated per tenant
- [ ] Cannot access other tenant's medical profiles

---

## Key Differences from Old Implementation

The old implementation was based on "Antimicrobial Resistance" (lab tests for bacterial cultures). This new implementation is "Ambulatory Medical Records" - a comprehensive patient medical profile:

| Old (Antimicrobial Resistance) | New (Ambulatory Medical Records) |
|--------------------------------|----------------------------------|
| Lab test records | Complete medical profile |
| Antibiotic sensitivities | Allergies, medications, contraindications |
| MDRO flags | Skin assessments, lifestyle info |
| Single summary table | Multiple related tables |
| Test-based approach | Profile-based approach |

---

## Notes

- All tables use UUIDs for primary keys (consistent with existing schema)
- All tables include tenant_id for multi-tenancy
- JSONB columns used for flexible array storage (PostgreSQL)
- Soft deletes enabled for all medical records
- Audit fields (created_by, updated_by) for tracking
- Review tracking for compliance
- Fitzpatrick skin type stored in both profile and assessment for different contexts
