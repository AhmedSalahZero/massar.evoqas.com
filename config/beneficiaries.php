<?php

// ══════════════════════════════════════════════════════════════════
//  Massar — Beneficiary settings
//  Location: config/beneficiaries.php
//
//  The fixed choices on the beneficiary form, in ONE place. The form,
//  the list filters and the server checks all read these lists, so a
//  choice can never be offered on screen but refused on saving (or
//  the other way round). The words shown for each code are in
//  resources/js/lang/translations.js (ben.gender.*, ben.edu.* …;
//  governorates use the existing gov.* labels).
//
//  Adding a choice: add its code here and its en/ar label in
//  translations.js. Removing one: only if no beneficiary uses it.
// ══════════════════════════════════════════════════════════════════

return [

    // The 27 governorates, same codes as partner organisations use.
    'governorates' => [
        'cai', 'giz', 'alx', 'qal', 'dak', 'sha', 'gha', 'mnf', 'beh', 'kfs', 'dam', 'pts', 'ism', 'suz',
        'fay', 'bns', 'min', 'ast', 'soh', 'qen', 'lux', 'asw', 'red', 'wad', 'mat', 'nsi', 'ssi',
    ],

    'genders' => ['male', 'female'],

    // Asked for men only.
    'military_statuses' => ['completed', 'exempted', 'postponed', 'not_yet', 'serving'],

    // Highest level completed, Egyptian system.
    'education_levels' => [
        'none', 'read_write', 'primary', 'preparatory', 'secondary_general', 'secondary_technical',
        'above_intermediate', 'university', 'postgraduate',
    ],

    'languages' => ['ar', 'en', 'fr', 'de', 'it', 'es', 'ru', 'zh', 'tr', 'other'],

    'language_levels' => ['native', 'fluent', 'good', 'basic'],

    'job_types' => ['full_time', 'part_time', 'temporary', 'any'],

    // Limits that keep one profile a sensible size.
    'max' => [
        'education'    => 10,
        'work_history' => 20,
        'responsibilities' => 30,   // duties under one job, each up to 500 characters
        'skills'       => 40,
        'languages'    => 10,
    ],

    // Expected-salary check: within ± this percentage of a market
    // figure counts as "close to" it.
    'salary_close_pct' => 15,

    'per_page' => 25,
];
