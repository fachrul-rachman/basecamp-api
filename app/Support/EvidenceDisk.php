<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

class EvidenceDisk
{
    public static function name(): string
    {
        return config('filesystems.evidence_disk');
    }

    public static function disk(): Filesystem
    {
        return Storage::disk(self::name());
    }

    public static function url(string $path): string
    {
        $disk = self::name();

        if (config("filesystems.disks.{$disk}.driver") === 's3') {
            return self::disk()->temporaryUrl(
                $path,
                now()->addMinutes(config('filesystems.evidence_url_ttl_minutes'))
            );
        }

        return self::disk()->url($path);
    }
}
