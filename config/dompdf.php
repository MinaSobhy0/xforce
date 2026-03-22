<?php

/**
 * DomPDF Configuration
 *
 * SECURITY: Hardened configuration for PDF generation.
 * This config overrides vendor defaults with secure settings.
 */

return [

    'show_warnings' => false,

    'public_path' => null,

    'convert_entities' => true,

    'options' => [
        'font_dir' => storage_path('fonts'),

        'font_cache' => storage_path('fonts'),

        'temp_dir' => sys_get_temp_dir(),

        /**
         * SECURITY: Restrict chroot to prevent file system access
         * Only allow access to application directory
         */
        'chroot' => realpath(base_path()),

        /**
         * SECURITY: Restrict allowed protocols
         * Only allow data: URIs for embedded images
         * Disable file://, http://, https:// to prevent SSRF
         */
        'allowed_protocols' => [
            'data://' => ['rules' => []],
            // SECURITY: Disabled to prevent SSRF attacks
            // 'file://' => ['rules' => []],
            // 'http://' => ['rules' => []],
            // 'https://' => ['rules' => []],
        ],

        'artifactPathValidation' => null,

        'log_output_file' => null,

        'enable_font_subsetting' => true,

        'pdf_backend' => 'CPDF',

        'default_media_type' => 'screen',

        'default_paper_size' => 'a4',

        'default_paper_orientation' => 'portrait',

        'default_font' => 'serif',

        'dpi' => 96,

        /**
         * SECURITY: Disable embedded PHP execution
         * This prevents arbitrary code execution via PDF
         */
        'enable_php' => false,

        /**
         * SECURITY: Disable JavaScript in PDFs
         * PDF JavaScript can be used for phishing or malicious behavior
         */
        'enable_javascript' => false,

        /**
         * SECURITY: Disable remote file access
         * Prevents SSRF attacks via remote image/CSS loading
         */
        'enable_remote' => false,

        /**
         * SECURITY: No allowed remote hosts since remote is disabled
         */
        'allowed_remote_hosts' => [],

        'font_height_ratio' => 1.1,

        'enable_html5_parser' => true,
    ],

];
