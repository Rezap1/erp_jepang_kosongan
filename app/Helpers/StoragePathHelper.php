<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;

class StoragePathHelper
{
    public static function normalizePrivateStoredPath($storedPath): ?string
    {
        if (!is_string($storedPath)) {
            return null;
        }

        $relativePath = trim(str_replace('\\', '/', $storedPath));
        if ($relativePath === '' || str_contains($relativePath, "\0")) {
            return null;
        }

        if (str_starts_with($relativePath, 'storage/')) {
            $relativePath = substr($relativePath, strlen('storage/'));
        }

        $relativePath = ltrim($relativePath, '/');
        if ($relativePath === '' || preg_match('#(^|/)\.\.(/|$)#', $relativePath) || preg_match('/^[A-Za-z]:/', $relativePath)) {
            return null;
        }

        if (str_starts_with(strtolower($relativePath), 'http://') || str_starts_with(strtolower($relativePath), 'https://')) {
            return null;
        }

        return $relativePath;
    }

    public static function privateFileResponsePath($storedPath): ?string
    {
        $relativePath = self::normalizePrivateStoredPath($storedPath);
        if ($relativePath === null) {
            return null;
        }

        if (Storage::disk('local')->exists($relativePath)) {
            return Storage::disk('local')->path($relativePath);
        }

        $legacyPath = storage_path('app/' . $relativePath);
        $realLegacyPath = realpath($legacyPath);
        $realStorageRoot = realpath(storage_path('app'));

        if ($realLegacyPath && $realStorageRoot) {
            $rootPrefix = rtrim($realStorageRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            if (str_starts_with($realLegacyPath, $rootPrefix)) {
                return $realLegacyPath;
            }
        }

        return null;
    }
}
