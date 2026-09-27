<?php

// ══════════════════════════════════════════════════════════════════
//  Massar — Occupation Backbone settings
//  Location: config/backbone.php
//
//  Where the official source files live and which edition each one
//  is. The importer (php artisan backbone:import) reads ONLY these
//  files, so updating the backbone later means: replace the file in
//  database/data/backbone, change its edition label here, run the
//  import again. Existing occupation IDs are kept, so everything
//  already classified (CVs, beneficiaries, jobs) stays valid.
//
//  Files (all in database/data/backbone):
//    isco_en   ILO ISCO-08 structure with English definitions
//    isco_ar   ILO ISCO-08 Arabic titles (Arab Region translation)
//    enoc      Egypt Occupational Outlook — ENOC 2006 codes, Arabic
//              titles and task descriptions (the labour market
//              columns are imported in the next step)
//    esco_en   ESCO occupations, English (esco/occupations_en.csv)
//    esco_ar   ESCO occupations, Arabic  (esco/occupations_ar.csv)
//
//  SKILLS (Step 5) — see 'skills' below. ESCO's skills pillar, loaded
//  by `php artisan skills:import` AFTER backbone:import (skills are
//  linked to the ESCO occupations above by their permanent URI). The
//  skills files must be from the SAME ESCO edition as the occupations.
//
//  LABOUR MARKET (Step 2) — see 'market' below. The Egypt
//  Occupational Outlook figures are loaded as dated EDITIONS by
//  `php artisan market:import`; one edition is "current" and is what
//  every screen shows. A new file never overwrites an old edition.
// ══════════════════════════════════════════════════════════════════

return [

    'path' => env('BACKBONE_PATH', database_path('data/backbone')),

    'files' => [
        'isco_en' => 'ISCO-08_EN_Structure_and_definitions.xlsx',
        'isco_ar' => 'ISCO_08_AR_structure_V1_0.xlsx',
        'enoc'    => 'Egypt_Occupational_Outlook.xlsx',
        'esco_en' => 'esco/occupations_en.csv',
        'esco_ar' => 'esco/occupations_ar.csv',
    ],

    // Shown on the Backbone screen and saved with every import.
    'editions' => [
        'isco' => 'ISCO-08 (ILO) · Arabic v1.0, 2021',
        'enoc' => 'ENOC 2006 · Egypt Occupational Outlook',
        'esco' => 'ESCO v1.2.1',
    ],

    // ── ESCO skills pillar (Step 5) ─────────────────────────────────
    //  All in database/data/backbone/esco, straight from the ESCO
    //  download (CSV, English + Arabic). The files that only link
    //  things together (the last three) contain no text, so the English
    //  copy is enough: the Arabic copies are identical.
    //  To load a newer ESCO: replace these AND the two occupation files
    //  above, change editions.esco, run backbone:import, then
    //  skills:import.
    'skills' => [
        'files' => [
            'skills_en'   => 'esco/skills_en.csv',
            'skills_ar'   => 'esco/skills_ar.csv',
            'groups_en'   => 'esco/skillGroups_en.csv',
            'groups_ar'   => 'esco/skillGroups_ar.csv',
            'broader'     => 'esco/broaderRelationsSkillPillar_en.csv',
            'occupations' => 'esco/occupationSkillRelations_en.csv',
            'related'     => 'esco/skillSkillRelations_en.csv',
        ],
    ],

    // Rows written per database round-trip during an import.
    'chunk' => 500,

    // ── Egypt labour market data (Egypt Occupational Outlook) ────────
    //  To load a newer file: put it in database/data/backbone, set
    //  'file' and the three labels below, run `php artisan market:import`,
    //  review the comparison, then `php artisan market:use <edition id>`.
    'market' => [
        'file'             => 'Egypt_Occupational_Outlook.xlsx',
        'edition'          => 'Egypt Occupational Outlook · platform launch, April 2025',
        'edition_ar'       => 'آفاق المهن والتوظيف · إطلاق المنصة، أبريل 2025',
        'reference_period' => '2017–2021',
        // true = the period is our estimate, not yet confirmed by the
        // Ministry of Planning; screens then say "about … (estimate)".
        'reference_is_estimate' => true,
        'source'           => 'Ministry of Planning, Economic Development and International Cooperation · GIZ Employment Promotion Project',

        // Wages are shown with a note that they date from the survey
        // period. Set to false to hide wages from partner screens.
        'show_wages_to_partners' => true,
    ],
];
