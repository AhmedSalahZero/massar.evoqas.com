<?php

// ══════════════════════════════════════════════════════════════════
//  Massar — Beneficiary messages (English)
//  Location: lang/en/beneficiaries.php
//
//  What the server says about beneficiary profiles: confirmations and
//  the reasons a profile cannot be saved yet. Screen labels are in
//  resources/js/lang/translations.js. Keep key-for-key in sync with
//  lang/ar/beneficiaries.php.
// ══════════════════════════════════════════════════════════════════

return [
    'registered' => 'Beneficiary No. :number registered.',
    'saved'      => 'Profile saved.',

    // Field names used inside messages
    'f' => [
        'name_ar'         => 'Arabic name',
        'name_en'         => 'English name',
        'gender'          => 'gender',
        'date_of_birth'   => 'date of birth',
        'military_status' => 'military service',
        'governorate'     => 'governorate',
        'city'            => 'city or area',
        'phone'           => 'mobile number',
        'email'           => 'email',
        'education_level' => 'education level',
        'expected_salary' => 'expected salary',
        'job_type'        => 'job type',
    ],

    // Reasons a profile cannot be saved
    'v' => [
        'name'              => 'Enter the name in Arabic, in English, or both.',
        'contact'           => 'Enter a mobile number or an email, so the person can be reached.',
        'phone'             => 'Enter an Egyptian mobile number: 11 digits starting with 010, 011, 012 or 015.',
        'dob'               => 'Enter a real date of birth, in the past.',
        'qualification'     => 'Enter the qualification, or remove this row.',
        'job_title'         => 'Enter the job title, or remove this row.',
        'month'             => 'Choose the month.',
        'duties_many'       => 'Up to 30 responsibilities per job. Join or remove some lines.',
        'duty_long'         => 'One responsibility is too long (500 characters at most). Split it into two lines.',
        'month_future'      => 'This month has not happened yet.',
        'end_before_start'  => 'The end is before the start.',
        'end_or_current'    => 'Choose the end month, or tick "Current job".',
        'language_twice'    => 'This language is listed twice.',
        'language_level'    => 'Choose the level.',
        'occupation'        => 'This occupation is no longer in the backbone. Choose it again.',
        'occupation_one'    => 'Choose one occupation.',
        'sector'            => 'Choose a sub-sector from the list.',
        'employer'          => 'That company is not in the list any more. Choose it again or type its name.',
        'occupation_detail' => 'Choose the detailed job inside this group.',
        'salary'            => 'Enter the monthly amount in Egyptian pounds, in whole numbers, e.g. 7500.',
        'duplicate'         => 'Someone in your workspace already has this mobile or email: :names. Open that profile instead, or confirm this is a different person.',
    ],
];
