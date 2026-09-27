<?php

// ══════════════════════════════════════════════════════════════════
//  Massar — CV Bank settings
//  Location: config/cv.php
//  Scope v2 §3 Bulk CV Upload · CV Reading Engine, §7 Secure CV Storage
//
//  pdftotext        The Poppler program that reads the text out of PDF
//                   files. On Windows put its full path in .env, with
//                   forward slashes, for example:
//                       PDFTOTEXT_PATH=C:/poppler/Library/bin/pdftotext.exe
//                   Word files (.docx) need nothing extra.
//  max_files        files in one upload (Scope: up to 50)
//  max_kb           largest CV file accepted, in KB (5 MB). PHP's own
//                   upload limit (upload_max_filesize in php.ini) must
//                   be at least this big — see docs/STEP_06_CV_BANK.md.
//  min_letters      a PDF with fewer letters than this is treated as a
//                   scan (a picture of text), which needs OCR (later).
//  disk             where original files are kept: the private 'cvs'
//                   disk (config/filesystems.php → storage/app/private/cvs),
//                   never reachable from the web; one folder per partner.
//                   Each file is encrypted with the APP_KEY before it is
//                   written, so a copied folder is unreadable without it.
//                   ⚠ Keep your APP_KEY safe: without it the stored CV
//                   files cannot be opened.
//  auto_add         true  → a CV read with nothing uncertain becomes a
//                           profile at once (status "added").
//                   false → every CV waits in the review queue.
// ══════════════════════════════════════════════════════════════════

return [
    'pdftotext'   => env('PDFTOTEXT_PATH', 'pdftotext'),
    'timeout'     => 30,          // seconds allowed to read one PDF
    'max_files'   => 50,
    'max_kb'      => 5120,
    'extensions'  => ['pdf', 'docx', 'doc'],
    'min_letters' => 60,
    'disk'        => 'cvs',
    'auto_add'    => true,
    'per_page'    => 25,
];
