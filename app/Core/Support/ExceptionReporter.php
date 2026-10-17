<?php

namespace Flute\Core\Support;

use Flute\Core\Services\CrashReportService;
use Throwable;

final class ExceptionReporter
{
    private const MAX_MESSAGE_LENGTH = 2000;
    private const MAX_BODY_LENGTH = 8000;
    private const MAX_ARRAY_ITEMS = 80;
    private const MAX_ARRAY_DEPTH = 5;
    private const REPLAY_HEADERS = [
        'CONTENT_TYPE',
        'HTTP_ACCEPT',
        'HTTP_USER_AGENT',
        'HTTP_X_REQUESTED_WITH',
        'HTTP_HX_REQUEST',
        'HTTP_HX_TARGET',
        'HTTP_HX_TRIGGER',
        'HTTP_X_CSRF_TOKEN',
    ];

    public static function report(
        Throwable $exception,
        string $source,
        array $context = [],
        string $level = 'error',
        bool $sendRemote = true,
    ): void {
        $fingerprint = self::fingerprint($exception, $source);
        $context = self::buildContext($exception, $source, $fingerprint, $context);

        self::writeLocal($exception, $source, $fingerprint, $context, $level);

        if ($sendRemote) {
            CrashReportService::capture($exception, [
                'source' => $source,
                'fingerprint' => $fingerprint,
            ] + $context);
        }
    }

    public static function reportFatal(array $error, string $source = 'fatal'): void
    {
        $message = (string) ( $error['message'] ?? 'Unknown fatal error' );
        $file = (string) ( $error['file'] ?? '' );
        $line = (int) ( $error['line'] ?? 0 );
        $fingerprint = md5('FatalError:' . $file . ':' . $line . ':' . $source);

        $payload = [
            'source' => $source,
            'fingerprint' => $fingerprint,
            'exception_class' => 'FatalError',
            'message' => self::scrub($message),
            'file' => self::relativePath($file),
            'line' => $line,
            'request' => self::requestInfo(),
        ];

        self::appendEmergencyLog($payload);
    }

    public static function reportHttpFailure(int $status, ?string $message = null, string $source = 'response'): void
    {
        if ($status < 500) {
            return;
        }

        $fingerprint = md5(
            'HttpFailure:' . $status . ':' . self::requestInfo()['method'] . ':' . self::requestInfo()['uri'],
        );
        $payload = [
            'source' => $source,
            'fingerprint' => $fingerprint,
            'status' => $status,
            'message' => self::scrub((string) ( $message ?? 'HTTP ' . $status )),
            'request' => self::requestInfo(),
            'crash_report' => false,
        ];

        try {
            if (function_exists('logs')) {
                logs('exceptions')->error(
                    sprintf('[%s] HTTP %d response generated: %s', $fingerprint, $status, $payload['message']),
                    $payload,
                );

                return;
            }
        } catch (Throwable $loggingFailure) {
            $payload['logger_error'] = $loggingFailure->getMessage();
        }

        self::appendEmergencyLog($payload);
    }

    private static function writeLocal(
        Throwable $exception,
        string $source,
        string $fingerprint,
        array $context,
        string $level,
    ): void {
        $message = sprintf(
            '[%s] %s: %s in %s:%d',
            $fingerprint,
            $exception::class,
            self::truncate($exception->getMessage()),
            self::relativePath($exception->getFile()),
            $exception->getLine(),
        );

        $context['exception'] = $exception;
        $context['crash_report'] = false;

        try {
            if (function_exists('logs')) {
                $logger = logs('exceptions');
                if (method_exists($logger, $level)) {
                    $logger->{$level}($message, $context);
                } else {
                    $logger->error($message, $context);
                }

                return;
            }
        } catch (Throwable $loggingFailure) {
            $context['logger_error'] = $loggingFailure->getMessage();
        }

        self::appendEmergencyLog([
            'source' => $source,
            'fingerprint' => $fingerprint,
            'exception_class' => $exception::class,
            'message' => self::scrub($exception->getMessage()),
            'file' => self::relativePath($exception->getFile()),
            'line' => $exception->getLine(),
            'request' => self::requestInfo(),
            'logger_error' => $context['logger_error'] ?? null,
        ]);
    }

    private static function buildContext(
        Throwable $exception,
        string $source,
        string $fingerprint,
        array $context,
    ): array {
        return (
            [
                'source' => $source,
                'fingerprint' => $fingerprint,
                'exception_class' => $exception::class,
                'exception_file' => self::relativePath($exception->getFile()),
                'exception_line' => $exception->getLine(),
                'request' => self::requestInfo(),
            ] + self::scrubArray($context)
        );
    }

    private static function appendEmergencyLog(array $payload): void
    {
        $line =
            '['
            . date('Y-m-d H:i:s')
            . '] '
            . ( json_encode(self::scrubArray($payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}' )
            . PHP_EOL;

        $path = self::emergencyLogPath();
        if ($path !== null && @file_put_contents($path, $line, FILE_APPEND | LOCK_EX) !== false) {
            return;
        }

        @error_log($line);
    }

    private static function emergencyLogPath(): ?string
    {
        $base = defined('BASE_PATH') ? rtrim(BASE_PATH, "\\/") : dirname(__DIR__, 3);
        $dir = $base . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';

        if (!is_dir($dir) && !@mkdir($dir, 0o775, true) && !is_dir($dir)) {
            return null;
        }

        return $dir . DIRECTORY_SEPARATOR . 'exceptions-emergency.log';
    }

    private static function fingerprint(Throwable $exception, string $source): string
    {
        return md5($exception::class . ':' . $exception->getFile() . ':' . $exception->getLine() . ':' . $source);
    }

    private static function requestInfo(): array
    {
        $basic = [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'uri' => self::scrub((string) ( $_SERVER['REQUEST_URI'] ?? '' )),
            'host' => self::scrub((string) ( $_SERVER['HTTP_HOST'] ?? '' )),
            'ip' => self::scrub((string) ( $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '' )),
        ];

        if (( $basic['method'] ?? 'CLI' ) === 'CLI') {
            return $basic;
        }

        $contentType = (string) ( $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '' );
        $rawBody = self::readRawBody();
        $jsonBody = self::decodeJsonBody($rawBody, $contentType);
        $headers = self::requestHeaders();

        return $basic
        + [
            'scheme' => self::requestScheme(),
            'url' => self::fullUrl(),
            'content_type' => self::scrub($contentType),
            'content_length' => isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : null,
            'headers' => $headers,
            'query' => self::sanitizeValue($_GET),
            'post' => self::sanitizeValue($_POST),
            'json' => $jsonBody,
            'raw_body' => $rawBody,
            'files' => self::sanitizeFiles($_FILES),
            'curl' => self::buildCurlReplay($headers, $rawBody),
        ];
    }

    private static function readRawBody(): ?string
    {
        if (in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD', 'OPTIONS'], true)) {
            return null;
        }

        $body = @file_get_contents('php://input');
        if (!is_string($body) || $body === '') {
            return null;
        }

        return self::scrub(self::truncateBody($body));
    }

    private static function decodeJsonBody(?string $rawBody, string $contentType): mixed
    {
        if ($rawBody === null || !str_contains(strtolower($contentType), 'json')) {
            return null;
        }

        $decoded = json_decode($rawBody, true);

        return json_last_error() === JSON_ERROR_NONE ? self::sanitizeValue($decoded) : null;
    }

    private static function requestHeaders(): array
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            if ($key === 'CONTENT_TYPE' || $key === 'CONTENT_LENGTH' || str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', preg_replace('/^HTTP_/', '', $key)));
                $headers[$name] = self::isSensitiveKey($name) ? '[REDACTED]' : self::sanitizeValue((string) $value);
            }
        }

        ksort($headers);

        return $headers;
    }

    private static function sanitizeFiles(array $files): array
    {
        $result = [];

        foreach ($files as $field => $file) {
            if (!is_array($file)) {
                continue;
            }

            $result[$field] = [
                'name' => self::sanitizeValue($file['name'] ?? null),
                'type' => self::sanitizeValue($file['type'] ?? null),
                'size' => self::sanitizeValue($file['size'] ?? null),
                'error' => self::sanitizeValue($file['error'] ?? null),
            ];
        }

        return $result;
    }

    private static function buildCurlReplay(array $headers, ?string $rawBody): string
    {
        $method = strtoupper((string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ));
        $parts = ['curl', '-i', '-X', self::shellArg($method), self::shellArg(self::fullUrl())];

        foreach (self::REPLAY_HEADERS as $serverKey) {
            $headerName = strtolower(str_replace('_', '-', preg_replace('/^HTTP_/', '', $serverKey)));
            if (!isset($headers[$headerName])) {
                continue;
            }

            $parts[] = '-H';
            $parts[] = self::shellArg($headerName . ': ' . (string) $headers[$headerName]);
        }

        if ($rawBody !== null) {
            $parts[] = '--data-raw';
            $parts[] = self::shellArg($rawBody);
        } elseif (!empty($_POST)) {
            $parts[] = '--data-raw';
            $parts[] = self::shellArg(http_build_query((array) self::sanitizeValue($_POST)));
        }

        return implode(' ', $parts);
    }

    private static function requestScheme(): string
    {
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            return strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https' ? 'https' : 'http';
        }

        return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
    }

    private static function fullUrl(): string
    {
        $host = (string) ( $_SERVER['HTTP_HOST'] ?? 'localhost' );
        $uri = (string) ( $_SERVER['REQUEST_URI'] ?? '/' );

        return self::requestScheme() . '://' . $host . $uri;
    }

    private static function sanitizeValue(mixed $value, int $depth = 0): mixed
    {
        if ($depth > self::MAX_ARRAY_DEPTH) {
            return '[MAX_DEPTH]';
        }

        if (is_array($value)) {
            $result = [];
            $count = 0;
            foreach ($value as $key => $item) {
                if ($count >= self::MAX_ARRAY_ITEMS) {
                    $result['__truncated'] = true;

                    break;
                }

                $stringKey = (string) $key;
                if (self::isSensitiveKey($stringKey)) {
                    $result[$stringKey] = '[REDACTED]';
                } else {
                    $result[$stringKey] = self::sanitizeValue($item, $depth + 1);
                }
                $count++;
            }

            return $result;
        }

        if (is_string($value)) {
            return self::scrub(self::truncateBody($value));
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        return is_object($value) ? $value::class : gettype($value);
    }

    private static function truncateBody(string $body): string
    {
        return strlen($body) > self::MAX_BODY_LENGTH
            ? substr($body, 0, self::MAX_BODY_LENGTH) . '...[TRUNCATED]'
            : $body;
    }

    private static function shellArg(string $value): string
    {
        return "'" . str_replace("'", "'\"'\"'", $value) . "'";
    }

    private static function isSensitiveKey(string $key): bool
    {
        return (bool) preg_match(
            '/password|passwd|pwd|secret|token|csrf|key|auth|cookie|session|credential|dsn|api_key|authorization|signature|sign/i',
            $key,
        );
    }

    private static function relativePath(string $path): string
    {
        $base = defined('BASE_PATH') ? str_replace('\\', '/', rtrim(BASE_PATH, "\\/") . '/') : '';
        $normalized = str_replace('\\', '/', $path);

        return $base !== '' && str_starts_with($normalized, $base) ? substr($normalized, strlen($base)) : $normalized;
    }

    private static function truncate(string $message): string
    {
        return strlen($message) > self::MAX_MESSAGE_LENGTH
            ? substr($message, 0, self::MAX_MESSAGE_LENGTH) . '...'
            : $message;
    }

    private static function scrubArray(array $payload): array
    {
        foreach ($payload as $key => $value) {
            $lowerKey = strtolower((string) $key);
            if (self::isSensitiveKey($lowerKey)) {
                $payload[$key] = '[REDACTED]';

                continue;
            }

            if (is_string($value)) {
                $payload[$key] = self::scrub($value);
            } elseif (is_array($value)) {
                $payload[$key] = self::scrubArray($value);
            } elseif ($value instanceof Throwable) {
                $payload[$key] = $value::class . ': ' . self::truncate($value->getMessage());
            } elseif (is_object($value)) {
                $payload[$key] = $value::class;
            }
        }

        return $payload;
    }

    private static function scrub(string $value): string
    {
        $value = self::truncate($value);

        $value = (string) preg_replace(
            '/(password|passwd|pwd|secret|token|csrf|key|auth|cookie|session|credential|dsn|api_key|authorization|signature|sign)\s*[=:]\s*[^\s,;&]+/i',
            '$1=[REDACTED]',
            $value,
        );

        return (string) preg_replace(
            '/("(?:password|passwd|pwd|secret|token|csrf|key|auth|cookie|session|credential|dsn|api_key|authorization|signature|sign)"\s*:\s*)"[^"]*"/i',
            '$1"[REDACTED]"',
            $value,
        );
    }
}
