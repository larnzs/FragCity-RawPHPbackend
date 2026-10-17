<?php

namespace Flute\Core\Cache;

/**
 * Sweeps orphan ".!XXXXX" temp directories left behind by Symfony\Filesystem
 * when a recursive remove() is interrupted (FPM kill, OOM, lost connection).
 *
 * Symfony\Component\Filesystem\Filesystem::doRemove() renames the target dir
 * to "{parent}/.!" + base64(random_bytes(2)) before recursive unlinking.
 * If the worker dies before unlink+rmdir finish, the renamed dir survives
 * and accumulates over time, eating inodes and confusing later clears.
 */
final class OrphanSweeper
{
    public static function sweep(string $parentDir, int $minAgeSeconds = 3600): int
    {
        if (!is_dir($parentDir)) {
            return 0;
        }

        $removed = 0;
        $cutoff = time() - $minAgeSeconds;

        $dh = @opendir($parentDir);
        if ($dh === false) {
            return 0;
        }

        while (( $entry = readdir($dh) ) !== false) {
            if (!str_starts_with($entry, '.!')) {
                continue;
            }

            $path = $parentDir . DIRECTORY_SEPARATOR . $entry;
            if (!is_dir($path)) {
                continue;
            }

            $mtime = @filemtime($path);
            if ($mtime === false || $mtime > $cutoff) {
                continue;
            }

            if (self::removeTree($path)) {
                $removed++;
            }
        }

        closedir($dh);

        return $removed;
    }

    private static function removeTree(string $path): bool
    {
        if (is_link($path) || is_file($path)) {
            return @unlink($path);
        }

        if (!is_dir($path)) {
            return false;
        }

        $dh = @opendir($path);
        if ($dh === false) {
            return false;
        }

        while (( $entry = readdir($dh) ) !== false) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            self::removeTree($path . DIRECTORY_SEPARATOR . $entry);
        }
        closedir($dh);

        return @rmdir($path);
    }
}
