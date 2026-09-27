<?php

// ══════════════════════════════════════════════════════════════════
//  Massar — CV Bank messages (English)
//  Location: lang/en/cv.php
//  What the server says about CV uploads and reviews. Screen labels
//  are in resources/js/lang/translations.js (cv.*). Keep key-for-key
//  in sync with lang/ar/cv.php.
// ══════════════════════════════════════════════════════════════════

return [
    'too_big'          => 'This file is larger than :mb MB.',
    'upload_failed'    => 'The file did not arrive. It may be larger than the server allows — ask your administrator to raise the upload limit.',
    'wrong_type'       => 'Only PDF and Word (.docx) files can be read.',
    'batch_full'       => 'This upload has already received all its files. Start a new upload.',
    'approved'         => 'Profile No. :number created from the CV.',
    'attached'         => 'The CV was added to profile No. :number.',
    'reread'           => 'The CV was read again with your current Learned Rules.',
    'no_pdf_reader'    => 'PDF files still cannot be read on this computer: the PDF reader (Poppler pdftotext) was not found. See docs/STEP_06_CV_BANK.md, step 1.',
    'updated'          => 'Profile No. :number was updated from the CV (:n changes) and the CV was added to it.',
    'update_invalid'   => 'These changes cannot be saved together: :errors',
    'rejected'         => 'The CV was rejected and its file deleted.',
    'already_decided'  => 'Someone has already dealt with this CV.',
    'attach_which'     => 'Enter the number of the profile to add this CV to.',
    'attach_not_found' => 'There is no profile No. :number in your workspace.',
];
