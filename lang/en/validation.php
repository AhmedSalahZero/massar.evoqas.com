<?php

// ══════════════════════════════════════════════════════════════════
//  Massar — Validation Strings (English)
//  Location: lang/en/validation.php
//
//  Deliberately thin: Laravel's own English messages are good. This
//  file adds the one message Laravel lacks (capital letter in a
//  password — App\Rules\ContainsUppercaseLetter) and, more
//  importantly, human field names in `attributes`, so a message
//  reads "The seat limit field is required" and not "The seat_limit
//  field is required". Keep attributes in step with lang/ar/validation.php.
// ══════════════════════════════════════════════════════════════════

return [

    'password' => [
        'uppercase' => 'The password must contain at least one capital letter (e.g. A).',
    ],

    'attributes' => [
        'name'                  => 'name',
        'name_ar'               => 'Arabic name',
        'email'                 => 'email',
        'phone'                 => 'mobile number',
        'password'              => 'password',
        'password_confirmation' => 'password confirmation',
        'current_password'      => 'current password',
        'language'              => 'language',
        'theme'                 => 'theme',
        'occupation_standard'   => 'occupation standard',
        'code'                  => 'verification code',
        'job_title'             => 'job title',
        'role'                  => 'role',
        'type'                  => 'organisation type',
        'governorate'           => 'governorate',
        'contact_email'         => 'contact email',
        'contact_phone'         => 'contact phone',
        'seat_limit'            => 'seat limit',
        'subscription_ends_at'  => 'subscription end date',
        'admin_name'            => 'administrator name',
        'admin_email'           => 'administrator email',
        'admin_job_title'       => 'administrator job title',
        'admin_language'        => 'administrator language',
        'admin_password'        => 'administrator password',
    ],

];
