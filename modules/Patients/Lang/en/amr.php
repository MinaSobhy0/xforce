<?php

return [
    // AMR Summary
    'amr_summary' => 'AMR Summary',
    'last_test_date' => 'Last Test Date',
    'mdro_flags' => 'MDRO Flags',
    'known_organisms' => 'Known Organisms',
    'known_resistances' => 'Known Resistances',
    'known_sensitivities' => 'Known Sensitivities',
    'alert_notes' => 'Alert Notes',
    'has_critical_resistance' => 'Critical Resistance',

    // Test Details
    'date' => 'Date',
    'specimen' => 'Specimen',
    'organism' => 'Organism',
    'mdro' => 'MDRO',
    'resistant' => 'Resistant',
    'sensitive' => 'Sensitive',
    'verified' => 'Verified',
    'laboratory' => 'Laboratory',

    // Form Fields
    'specimen_information' => 'Specimen Information',
    'collection_date' => 'Collection Date',
    'result_date' => 'Result Date',
    'specimen_source' => 'Specimen Source',
    'specimen_site' => 'Specimen Site',
    'specimen_site_placeholder' => 'e.g., Right arm, Left leg wound',
    'lab_accession_number' => 'Lab Accession Number',
    'laboratory_name' => 'Laboratory Name',

    // Organism
    'organism_identification' => 'Organism Identification',
    'organism_name' => 'Organism Name',
    'organism_code' => 'Organism Code',
    'custom_organism' => 'Custom Organism Name',
    'is_mdro' => 'Multi-Drug Resistant Organism (MDRO)',
    'is_mdro_help' => 'Check if this organism is resistant to multiple antibiotics',
    'mdro_types' => 'MDRO Types',

    // Antibiotic Results
    'antibiotic_results' => 'Antibiotic Sensitivity Results',
    'antibiotic' => 'Antibiotic',
    'sensitivity' => 'Sensitivity',
    'mic' => 'MIC Value',
    'mic_unit' => 'MIC Unit',
    'add_antibiotic_result' => 'Add Antibiotic Result',

    // Clinical Information
    'clinical_information' => 'Clinical Information',
    'clinical_notes' => 'Clinical Notes',
    'recommendations' => 'Treatment Recommendations',

    // Actions
    'verify' => 'Verify',
    'verify_test' => 'Verify AMR Test',
    'verify_description' => 'Are you sure you want to verify this AMR test result? This will mark it as clinically verified.',
    'test_verified' => 'AMR test verified successfully',

    // Filters
    'mdro_only' => 'MDRO Only',

    // Specimen Sources
    'specimen_sources' => [
        'blood' => 'Blood',
        'urine' => 'Urine',
        'sputum' => 'Sputum',
        'wound' => 'Wound',
        'stool' => 'Stool',
        'csf' => 'Cerebrospinal Fluid (CSF)',
        'respiratory' => 'Respiratory',
        'tissue' => 'Tissue',
        'abscess' => 'Abscess',
        'catheter' => 'Catheter',
        'swab' => 'Swab',
        'fluid' => 'Body Fluid',
        'biopsy' => 'Biopsy',
        'joint' => 'Joint Fluid',
        'eye' => 'Eye',
        'other' => 'Other',
    ],

    // Sensitivity Levels
    'sensitivity_levels' => [
        'S' => 'Sensitive (S)',
        'I' => 'Intermediate (I)',
        'R' => 'Resistant (R)',
        'SDD' => 'Susceptible-Dose Dependent (SDD)',
        'NS' => 'Non-Susceptible (NS)',
    ],

    // MDRO Types
    'mdro_types_list' => [
        'MRSA' => 'MRSA (Methicillin-resistant S. aureus)',
        'VRE' => 'VRE (Vancomycin-resistant Enterococcus)',
        'ESBL' => 'ESBL (Extended-spectrum beta-lactamase)',
        'CRE' => 'CRE (Carbapenem-resistant Enterobacteriaceae)',
        'MDRO' => 'Multi-drug Resistant Organism',
        'MRAB' => 'Multi-drug Resistant Acinetobacter',
        'CRPA' => 'Carbapenem-resistant P. aeruginosa',
        'PRSP' => 'Penicillin-resistant S. pneumoniae',
        'C_DIFF' => 'C. difficile (CDI)',
    ],

    // Antibiotic Classes
    'antibiotic_classes' => [
        'penicillins' => 'Penicillins',
        'cephalosporins' => 'Cephalosporins',
        'carbapenems' => 'Carbapenems',
        'aminoglycosides' => 'Aminoglycosides',
        'fluoroquinolones' => 'Fluoroquinolones',
        'macrolides' => 'Macrolides',
        'glycopeptides' => 'Glycopeptides',
        'tetracyclines' => 'Tetracyclines',
        'sulfonamides' => 'Sulfonamides',
        'other' => 'Other',
    ],
];
