<?php

namespace App\Services\Cv;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

// ══════════════════════════════════════════════════════════════════
//  Massar — CvStorage (original CV files, encrypted)
//  Location: app/Services/Cv/CvStorage.php
//  Scope v2 §7 Secure CV File Storage
//
//  Files are kept on the PRIVATE 'cvs' disk (config/filesystems.php), in
//  one folder per partner: storage/app/private/cvs/{company_id}/{uuid}.enc
//  They are encrypted with the app's APP_KEY before being written, so
//  the files are useless if the folder is copied. Nothing in /public
//  points at them: the only way to get one is the download address,
//  which checks the permission and writes the access log.
//
//  ⚠ Keep a safe copy of APP_KEY (in .env). If it is lost or changed,
//  the stored CV files can no longer be opened.
//  Later (Scope §7) the disk can be switched to S3-compatible storage
//  by changing CV_DISK_DRIVER — this class does not change.
// ══════════════════════════════════════════════════════════════════

class CvStorage
{
    public function put(int $companyId, string $uuid, string $contents): string
    {
        $path = $companyId.'/'.$uuid.'.enc';
        $this->disk()->put($path, Crypt::encryptString($contents));

        return $path;
    }

    public function get(string $path): ?string
    {
        $data = $this->disk()->get($path);

        return $data === null ? null : Crypt::decryptString($data);
    }

    public function delete(?string $path): void
    {
        if ($path) {
            $this->disk()->delete($path);
        }
    }

    /** Every file of one partner (used when the partner is deleted). */
    public function deleteWorkspace(int $companyId): void
    {
        $this->disk()->deleteDirectory((string) $companyId);
    }

    private function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk(config('cv.disk', 'cvs'));
    }
}
