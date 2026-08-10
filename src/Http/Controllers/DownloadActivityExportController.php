<?php

namespace MrAdder\FilamentLogger\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a queued export file.
 *
 * The signed URL alone is not treated as sufficient authority: the request must
 * also come from the user the export was generated for and pass the same export
 * ability that gates the download action in the panel.
 */
class DownloadActivityExportController
{
    public function __invoke(Request $request, string $owner, string $path): StreamedResponse
    {
        abort_unless(config('filament-logger.exports.enabled', true), 404);

        $user = $request->user();

        abort_if($user === null, 403);

        // A signed link forwarded to somebody else must not work.
        abort_unless((string) $user->getAuthIdentifier() === $owner, 403);

        $ability = config('filament-logger.exports.ability', 'exportActivity');

        if (is_string($ability) && filled($ability)) {
            abort_unless(
                Gate::allows($ability, ActivitylogServiceProvider::determineActivityModel()),
                403,
            );
        }

        $relativePath = $this->decodePath($path);

        abort_if($relativePath === null, 404);

        // The path is signed, but decode defensively anyway: never let a
        // traversal sequence out of the export directory.
        abort_unless($this->isWithinExportDirectory($relativePath, $owner), 403);

        $disk = Storage::disk((string) config('filament-logger.exports.queue.disk', 'local'));

        abort_unless($disk->exists($relativePath), 404);

        return $disk->download($relativePath, basename($relativePath));
    }

    protected function decodePath(string $encoded): ?string
    {
        $decoded = base64_decode(strtr($encoded, '-_', '+/'), true);

        return is_string($decoded) && $decoded !== '' ? $decoded : null;
    }

    protected function isWithinExportDirectory(string $path, string $owner): bool
    {
        if (str_contains($path, '..') || str_starts_with($path, '/')) {
            return false;
        }

        $expected = trim((string) config('filament-logger.exports.queue.path', 'filament-logger/exports'), '/')
            .'/'.$owner.'/';

        return str_starts_with($path, $expected);
    }
}
