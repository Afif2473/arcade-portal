<?php

namespace App\Actions;

use App\Models\Game;
use App\Models\GameVersion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class ProcessGameZipUploadAction
{
    // Allowed static asset extensions
    private const ALLOWED_EXTENSIONS = [
        'html', 'htm', 'js', 'mjs', 'css', 'json', 'wasm',
        'png', 'jpg', 'jpeg', 'webp', 'gif', 'svg', 'ico',
        'mp3', 'ogg', 'wav', 'aac', 'm4a',
        'glb', 'gltf', 'bin', 'woff', 'woff2', 'ttf', 'otf',
        'txt', 'xml', 'map',
    ];

    public function execute(Game $game, UploadedFile $zipFile, User $uploader): GameVersion
    {
        $zip = new ZipArchive();
        $openResult = $zip->open($zipFile->getRealPath());

        if ($openResult !== true) {
            throw ValidationException::withMessages([
                'bundle' => 'Could not read the uploaded archive. It may be corrupted.',
            ]);
        }

        $hasIndexHtml = false;
        $totalFiles = 0;
        $totalSize = 0;

        // 1. Airport Security Scan: Inspect every entry before extraction
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $entryName = $stat['name'];

            // Skip directory entries
            if (str_ends_with($entryName, '/')) {
                continue;
            }

            // Path Traversal (Zip Slip) Protection
            if (str_contains($entryName, '../') || str_starts_with($entryName, '/') || str_starts_with($entryName, '\\')) {
                $zip->close();
                throw ValidationException::withMessages([
                    'bundle' => "Security violation: archive contains illegal path traversal in '{$entryName}'.",
                ]);
            }

            // Verify index.html exists at the archive root
            if ($entryName === 'index.html') {
                $hasIndexHtml = true;
            }

            // Whitelist file extension check
            $extension = strtolower(pathinfo($entryName, PATHINFO_EXTENSION));
            if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
                $zip->close();
                throw ValidationException::withMessages([
                    'bundle' => "Prohibited file type detected: '{$entryName}'. Only static game assets are permitted.",
                ]);
            }

            $totalFiles++;
            $totalSize += $stat['size'];
        }

        if (! $hasIndexHtml) {
            $zip->close();
            throw ValidationException::withMessages([
                'bundle' => "Invalid game bundle: 'index.html' must be present at the root of the archive.",
            ]);
        }

        // 2. Determine Next Incremental Version Number
        $nextVersionNumber = ($game->versions()->max('version') ?? 0) + 1;
        $targetPrefix = "games/{$game->slug}/v{$nextVersionNumber}";
        $storageDisk = Storage::disk(config('filesystems.game_assets_disk'));

        // 3. Extract and store files under version prefix
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $entryName = $stat['name'];

            if (str_ends_with($entryName, '/')) {
                continue;
            }

            $content = $zip->getFromIndex($i);
            $targetPath = "{$targetPrefix}/{$entryName}";
            // index.html must always revalidate; versioned assets never change → cache forever
            $cacheControl = basename($entryName) === 'index.html'
                ? 'no-cache, must-revalidate'
                : 'public, max-age=31536000, immutable';

            $storageDisk->put($targetPath, $content, [
                'CacheControl' => $cacheControl,
            ]);
        }

        $zip->close();

        // 4. Record new version in Database atomically
        return DB::transaction(function () use ($game, $nextVersionNumber, $targetPrefix, $totalSize, $totalFiles, $uploader) {
            $version = GameVersion::create([
                'game_id' => $game->id,
                'version' => $nextVersionNumber,
                's3_prefix' => "{$targetPrefix}/",
                'source' => 'zip_upload',
                'status' => 'ready',
                'bundle_size' => $totalSize,
                'file_count' => $totalFiles,
                'uploaded_by' => $uploader->id,
            ]);

            // Set as the active version for the game
            $game->update([
                'active_version_id' => $version->id,
                'status' => $game->status === 'draft' ? 'sandbox' : $game->status,
            ]);

            return $version;
        });
    }
}
