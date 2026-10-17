<?php

namespace Flute\Core\Update\Updaters;

use Flute\Core\Database\Entities\Theme;
use Flute\Core\Support\FileUploader;
use Flute\Core\Theme\ThemeManager;
use RuntimeException;
use Throwable;

class ThemeUpdater extends AbstractUpdater
{
    /**
     * Информация о теме
     */
    protected Theme $theme;

    /**
     * Данные темы
     */
    protected array $themeData;

    protected ?string $backupDir = null;

    protected bool $preserveBackup = false;

    /**
     * ThemeUpdater constructor.
     */
    public function __construct(Theme $theme, array $themeData)
    {
        $this->theme = $theme;
        $this->themeData = $themeData;
    }

    public function getCurrentVersion(): string
    {
        return $this->theme->version ?? '1.0.0';
    }

    public function getIdentifier(): ?string
    {
        return $this->theme->key;
    }

    public function getType(): string
    {
        return 'theme';
    }

    public function getName(): string
    {
        return $this->theme->name;
    }

    public function getDescription(): string
    {
        return $this->theme->description;
    }

    public function update(array $data): bool
    {
        // Проверяем, есть ли файл с обновлением
        if (empty($data['package_file']) || !file_exists($data['package_file'])) {
            logs()->error('Theme update package file not found: ' . ( $data['package_file'] ?? 'null' ));

            return false;
        }

        $packageFile = $data['package_file'];
        $extractDir = storage_path('app/temp/updates/theme-' . $this->theme->key . '-' . time());
        $themeDir = $this->getThemeDirectory();
        $success = false;

        // Создаем временную директорию
        if (!is_dir($extractDir)) {
            mkdir($extractDir, 0o755, true);
        }

        try {
            app(FileUploader::class)->safeExtractZip($packageFile, $extractDir);

            $rootDir = $this->resolveThemeRoot($extractDir);
            $themeJson = $this->validateThemePackage($rootDir);

            $this->createBackup();

            $this->copyDirectory($rootDir, $themeDir);
            $this->updateThemeInformation($themeJson);

            $this->clearCache();

            $success = true;

            return true;
        } catch (Throwable $e) {
            logs()->error('Error during theme update: ' . $e->getMessage());
            $this->rollbackFromBackup($themeDir);

            \Flute\Core\Services\CrashReportService::capture($e, ['source' => 'update.theme']);

            return false;
        } finally {
            if (is_dir($extractDir)) {
                $this->removeDirectory($extractDir);
            }

            if ($success) {
                $this->cleanupTemporaryBackup();
            }
        }
    }

    /**
     * Получить путь к директории темы
     */
    protected function getThemeDirectory(): string
    {
        $basePath = dirname(dirname(dirname(dirname(__DIR__))));

        return $basePath . '/app/Themes/' . $this->theme->key;
    }

    /**
     * Создать бэкап перед обновлением
     */
    protected function createBackup(): bool
    {
        $themeDir = $this->getThemeDirectory();
        if (!is_dir($themeDir)) {
            return false;
        }

        $this->preserveBackup = (bool) config('app.create_backup');
        $backupRoot = $this->preserveBackup
            ? storage_path('backup/themes')
            : storage_path('app/temp/updates/rollback/themes');

        if (!is_dir($backupRoot) && !@mkdir($backupRoot, 0o755, true) && !is_dir($backupRoot)) {
            throw new RuntimeException('Unable to create theme backup directory.');
        }

        $this->backupDir = $backupRoot . '/' . $this->theme->key . '-' . date('Y-m-d-His') . '-' . getmypid();

        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0o755, true);
        }

        $this->copyDirectory($themeDir, $this->backupDir);

        logs()->info('Theme update rollback backup created: ' . $this->backupDir);

        return true;
    }

    /**
     * Копировать директорию рекурсивно
     */
    protected function copyDirectory(string $source, string $destination): bool
    {
        if (!is_dir($source)) {
            return false;
        }

        if (!is_dir($destination)) {
            $dirPerms = fileperms($source) & 0o777;
            mkdir($destination, $dirPerms, true);
            chmod($destination, $dirPerms);
            $this->safeChown($destination, fileowner($source));
            $this->safeChgrp($destination, filegroup($source));
        }

        $directory = opendir($source);
        if ($directory === false) {
            return false;
        }

        while (( $file = readdir($directory) ) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $sourcePath = $source . '/' . $file;
            $destinationPath = $destination . '/' . $file;

            if (is_dir($sourcePath)) {
                $this->copyDirectory($sourcePath, $destinationPath);
            } else {
                $this->atomicCopyFile($sourcePath, $destinationPath);
            }
        }

        closedir($directory);

        return true;
    }

    /**
     * Удалить директорию рекурсивно
     */
    protected function removeDirectory(string $directory): bool
    {
        if (!is_dir($directory)) {
            return false;
        }

        $items = scandir($directory);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . '/' . $item;

            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        return rmdir($directory);
    }

    /**
     * Очистить кэш
     */
    protected function clearCache(): void
    {
        // Очищаем кэш CSS и JS
        $assetsCachePath = public_path('assets/cache');
        if (is_dir($assetsCachePath)) {
            $this->removeDirectory($assetsCachePath);
            mkdir($assetsCachePath, 0o755, true);
        }

        // Очищаем кэш приложения
        cache()->forget('themes_list');
        cache()->forget('active_theme');
        cache()->deleteImmediately('flute.themes.get');
        cache()->deleteImmediately('flute.themes.json_data');
        cache()->deleteImmediately('flute.themes.db_rows');
        cache()->deleteImmediately('flute.themes.all');
        cache()->deleteImmediately('flute.global.layout');

        try {
            app(ThemeManager::class)->reInitThemes();
        } catch (Throwable $e) {
            logs()->warning('Failed to reinitialize themes after update: ' . $e->getMessage());
        }

        if (function_exists('cache_warmup_mark')) {
            cache_warmup_mark();
        }

        $this->resetOpcache();
    }

    protected function resolveThemeRoot(string $extractDir): string
    {
        $rootDir = $extractDir;
        $items = scandir($extractDir);
        if (is_array($items) && count($items) === 3) {
            foreach ($items as $item) {
                if ($item !== '.' && $item !== '..' && is_dir($extractDir . '/' . $item)) {
                    $rootDir = $extractDir . '/' . $item;

                    break;
                }
            }
        }

        return $rootDir;
    }

    protected function validateThemePackage(string $rootDir): array
    {
        $themeJsonPath = $rootDir . '/theme.json';
        if (!is_file($themeJsonPath)) {
            throw new RuntimeException('Invalid theme archive: theme.json not found.');
        }

        $themeJson = json_decode((string) file_get_contents($themeJsonPath), true);
        if (!is_array($themeJson) || empty($themeJson)) {
            throw new RuntimeException('Invalid theme archive: theme.json is invalid.');
        }

        return $themeJson;
    }

    protected function updateThemeInformation(array $themeJson): void
    {
        $theme = Theme::findOne(['key' => $this->theme->key]);
        if (!$theme) {
            throw new RuntimeException('Theme was not found after update.');
        }

        $theme->name = $themeJson['name'] ?? $theme->name;
        $theme->version = $themeJson['version'] ?? $theme->version;
        $theme->author = $themeJson['author'] ?? $theme->author;
        $theme->description = htmlspecialchars($themeJson['description'] ?? $theme->description);

        transaction($theme)->run();
    }

    protected function rollbackFromBackup(string $themeDir): void
    {
        if (!$this->backupDir || !is_dir($this->backupDir)) {
            return;
        }

        try {
            if (is_dir($themeDir)) {
                $this->removeDirectory($themeDir);
            }

            $this->copyDirectory($this->backupDir, $themeDir);
            logs()->warning('Theme update rolled back from backup: ' . $this->backupDir, [
                'theme' => $this->theme->key,
            ]);
        } catch (Throwable $rollbackError) {
            logs()->critical('Theme update rollback failed: ' . $rollbackError->getMessage(), [
                'theme' => $this->theme->key,
                'backup' => $this->backupDir,
            ]);
        } finally {
            if (!$this->preserveBackup && is_dir((string) $this->backupDir)) {
                $this->removeDirectory((string) $this->backupDir);
            }
        }
    }

    protected function cleanupTemporaryBackup(): void
    {
        if (!$this->preserveBackup && $this->backupDir && is_dir($this->backupDir)) {
            $this->removeDirectory($this->backupDir);
        }
    }

    /**
     * Получить путь к публичной директории
     */
    protected function public_path(string $path = ''): string
    {
        $basePath = dirname(dirname(dirname(dirname(__DIR__))));

        return $basePath . '/public/' . $path;
    }
}
