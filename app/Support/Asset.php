<?php

namespace App\Support;

/**
 * Appends the file's last-modified time as a query string, so a browser (or any cache in
 * between) is forced to fetch the new version the moment a CSS or JS file changes on the
 * server, instead of silently keeping an old cached copy after an update.
 */
class Asset
{
    public static function url(string $publicPath): string
    {
        $file = public_path($publicPath);
        $version = is_file($file) ? filemtime($file) : time();

        return asset($publicPath) . '?v=' . $version;
    }
}
