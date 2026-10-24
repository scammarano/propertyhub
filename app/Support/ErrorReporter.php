<?php
declare(strict_types=1);

/**
 * Registro centralizado de incidentes y diagnóstico.
 *
 * Reglas:
 * - Nunca registra contraseñas, tokens, cookies ni cabeceras de autorización.
 * - Cada petición recibe un request_id que permite correlacionar logs.
 * - Los errores fatales también quedan registrados mediante shutdown handler.
 */
final class ErrorReporter
{
    private static bool $booted = false;
    private static ?string $requestId = null;

    public static function bootstrap(): void
    {
        if (self::$booted) return;
        self::$booted = true;

        error_reporting(E_ALL);
        @ini_set('log_errors', '1');
        // Los errores se muestran mediante vistas controladas, no por salida cruda de PHP.
        @ini_set('display_errors', '0');
        @ini_set('display_startup_errors', '0');

        $dir = self::logDirectory();
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        if (is_dir($dir) && is_writable($dir)) {
            @ini_set('error_log', $dir.DIRECTORY_SEPARATOR.'php-runtime.log');
        }

        self::requestId();

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) return false;
            self::runtimeLog('php_error', [
                'severity' => $severity,
                'severity_name' => self::severityName($severity),
                'message' => self::sanitizeText($message),
                'file' => self::relativePath($file),
                'line' => $line,
            ]);
            // Se mantiene el manejador nativo. No convertimos avisos en excepciones para no romper producción.
            return false;
        });

        register_shutdown_function(static function (): void {
            $last = error_get_last();
            if (!is_array($last)) return;
            $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR];
            if (!in_array((int)($last['type'] ?? 0), $fatalTypes, true)) return;
            self::runtimeLog('fatal_shutdown', [
                'severity' => (int)($last['type'] ?? 0),
                'severity_name' => self::severityName((int)($last['type'] ?? 0)),
                'message' => self::sanitizeText((string)($last['message'] ?? 'Error fatal')), 
                'file' => self::relativePath((string)($last['file'] ?? '')),
                'line' => (int)($last['line'] ?? 0),
            ]);
        });

        if ((bool)config('app.log_requests', true)) {
            self::runtimeLog('request_start', [
                'query' => self::sanitizeArray($_GET ?? []),
            ]);
        }
    }

    public static function requestId(): string
    {
        if (self::$requestId !== null) return self::$requestId;
        $incoming = trim((string)($_SERVER['HTTP_X_REQUEST_ID'] ?? ''));
        if ($incoming !== '' && preg_match('/^[A-Za-z0-9._-]{8,100}$/', $incoming)) {
            self::$requestId = $incoming;
        } else {
            try {
                self::$requestId = date('YmdHis').'-'.substr(bin2hex(random_bytes(8)), 0, 12);
            } catch (Throwable) {
                self::$requestId = date('YmdHis').'-'.substr(sha1(uniqid('', true)), 0, 12);
            }
        }
        if (!headers_sent()) header('X-Request-ID: '.self::$requestId);
        return self::$requestId;
    }

    public static function report(Throwable $e, string|array $context='web'): string
    {
        try {
            $incident=date('YmdHis').'-'.substr(bin2hex(random_bytes(8)),0,8);
        } catch (Throwable) {
            $incident=date('YmdHis').'-'.substr(sha1(uniqid('', true)),0,8);
        }

        $contextData=[];
        if (is_array($context)) {
            $contextData=self::sanitizeArray($context);
            $contextName=trim((string)($contextData['operation'] ?? $contextData['context'] ?? 'web'));
        } else {
            $contextName=trim(self::sanitizeText($context));
        }
        if ($contextName==='') $contextName='web';

        $payload = self::baseContext() + [
            'event' => 'exception',
            'incident' => $incident,
            'context' => $contextName,
            'context_data' => $contextData,
            'exception' => get_class($e),
            'code' => (string)$e->getCode(),
            'message' => self::sanitizeText($e->getMessage()),
            'file' => self::relativePath($e->getFile()),
            'line' => $e->getLine(),
            'trace' => self::sanitizeText($e->getTraceAsString()),
            'previous' => self::previousChain($e),
            'get' => self::sanitizeArray($_GET ?? []),
            'post_fields' => array_values(array_map('strval', array_keys($_POST ?? []))),
        ];

        self::writeJson('incidents-'.date('Y-m-d').'.jsonl', $payload);
        self::writeText('property-hub-'.date('Y-m-d').'.log', self::formatTextIncident($payload));
        return $incident;
    }

    public static function authFailure(string $identifier, string $reason, ?Throwable $e=null): void
    {
        $payload = self::baseContext() + [
            'event' => 'auth_failure',
            'identifier' => self::maskIdentifier($identifier),
            'reason' => self::sanitizeText($reason),
            'exception' => $e ? get_class($e) : null,
            'file' => $e ? self::relativePath($e->getFile()) : null,
            'line' => $e?->getLine(),
        ];
        self::writeJson('auth-'.date('Y-m-d').'.jsonl', $payload);
    }

    public static function authSuccess(string $identifier, int|string|null $userId=null): void
    {
        self::writeJson('auth-'.date('Y-m-d').'.jsonl', self::baseContext() + [
            'event' => 'auth_success',
            'identifier' => self::maskIdentifier($identifier),
            'authenticated_user_id' => $userId,
        ]);
    }

    public static function runtimeLog(string $event, array $extra=[]): void
    {
        self::writeJson('runtime-'.date('Y-m-d').'.jsonl', self::baseContext() + [
            'event' => $event,
        ] + self::sanitizeArray($extra));
    }

    /**
     * Detalle seguro que puede mostrarse cuando APP_DIAGNOSTICS=true.
     */
    public static function publicDetails(Throwable $e): array
    {
        return [
            'request_id' => self::requestId(),
            'exception' => get_class($e),
            'message' => self::sanitizeText($e->getMessage()),
            'file' => self::relativePath($e->getFile()),
            'line' => $e->getLine(),
            'route' => function_exists('route_name') ? route_name() : '',
            'method' => (string)($_SERVER['REQUEST_METHOD'] ?? 'CLI'),
        ];
    }

    public static function logDirectory(): string
    {
        return function_exists('base_path') ? base_path('storage/logs') : dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'logs';
    }

    private static function baseContext(): array
    {
        $user = function_exists('current_user') ? current_user() : null;
        return [
            'timestamp' => date('c'),
            'request_id' => self::requestId(),
            'method' => (string)($_SERVER['REQUEST_METHOD'] ?? 'CLI'),
            'uri' => self::sanitizeText((string)($_SERVER['REQUEST_URI'] ?? '')),
            'route' => function_exists('route_name') ? route_name() : '',
            'remote_ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
            'forwarded_for' => self::sanitizeText((string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')),
            'user_agent' => self::sanitizeText((string)($_SERVER['HTTP_USER_AGENT'] ?? '')),
            'user_id' => is_array($user) ? ($user['id'] ?? null) : null,
            'user_email' => is_array($user) ? self::maskIdentifier((string)($user['email'] ?? '')) : 'guest',
            'instance_code' => function_exists('config') ? (string)config('app.instance_code', '') : '',
            'app_version' => function_exists('config') ? (string)config('app.version', '') : '',
            'php_version' => PHP_VERSION,
        ];
    }

    private static function previousChain(Throwable $e): array
    {
        $chain = [];
        $previous = $e->getPrevious();
        $limit = 0;
        while ($previous && $limit < 5) {
            $chain[] = [
                'exception' => get_class($previous),
                'message' => self::sanitizeText($previous->getMessage()),
                'file' => self::relativePath($previous->getFile()),
                'line' => $previous->getLine(),
            ];
            $previous = $previous->getPrevious();
            $limit++;
        }
        return $chain;
    }

    private static function formatTextIncident(array $p): string
    {
        $previous = empty($p['previous']) ? '' : "\nPrevious: ".json_encode($p['previous'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        return sprintf(
            "[%s] incident=%s request_id=%s context=%s method=%s route=%s uri=%s instance=%s user=%s ip=%s\n%s: %s\nfile=%s:%d%s\nTRACE\n%s\n\n",
            $p['timestamp'] ?? '', $p['incident'] ?? '', $p['request_id'] ?? '', $p['context'] ?? '',
            $p['method'] ?? '', $p['route'] ?? '', $p['uri'] ?? '', $p['instance_code'] ?? '',
            $p['user_email'] ?? '', $p['remote_ip'] ?? '', $p['exception'] ?? 'Throwable', $p['message'] ?? '',
            $p['file'] ?? '', (int)($p['line'] ?? 0), $previous, $p['trace'] ?? ''
        );
    }

    private static function writeJson(string $file, array $payload): void
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
        if (!is_string($json)) return;
        self::writeText($file, $json."\n");
    }

    private static function writeText(string $file, string $text): void
    {
        $dir = self::logDirectory();
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $path = $dir.DIRECTORY_SEPARATOR.basename($file);
        $ok = @file_put_contents($path, $text, FILE_APPEND|LOCK_EX);
        if ($ok === false) {
            // Último recurso: log nativo del servidor web/PHP.
            @error_log('[Property Hub] No se pudo escribir '.$path.' · '.substr($text, 0, 3000));
        }
    }

    private static function sanitizeArray(array $input): array
    {
        $output = [];
        foreach ($input as $key => $value) {
            $name = (string)$key;
            if (preg_match('/pass(word)?|token|secret|authorization|cookie|api[_-]?key|private[_-]?key|client[_-]?secret|recovery[_-]?key|backup[_-]?recovery/i', $name)) {
                $output[$name] = '[REDACTED]';
                continue;
            }
            if (is_array($value)) $output[$name] = self::sanitizeArray($value);
            elseif (is_scalar($value) || $value === null) $output[$name] = self::sanitizeText((string)$value);
            else $output[$name] = '['.get_debug_type($value).']';
        }
        return $output;
    }

    private static function sanitizeText(string $text): string
    {
        $text = preg_replace('/(password|passwd|pwd|token|secret|authorization|api[_-]?key|client[_-]?secret|recovery[_-]?key|backup[_-]?recovery)\s*[:=]\s*([^\s,;]+)/i', '$1=[REDACTED]', $text) ?? $text;
        $text = preg_replace('/Bearer\s+[A-Za-z0-9._~+\/-]+=*/i', 'Bearer [REDACTED]', $text) ?? $text;
        return $text;
    }

    private static function relativePath(string $path): string
    {
        if ($path === '') return '';
        $base = function_exists('base_path') ? rtrim(base_path(), DIRECTORY_SEPARATOR) : '';
        if ($base !== '' && str_starts_with($path, $base)) {
            return ltrim(substr($path, strlen($base)), DIRECTORY_SEPARATOR);
        }
        return basename($path);
    }

    private static function maskIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);
        if ($identifier === '') return '';
        if (str_contains($identifier, '@')) {
            [$local, $domain] = array_pad(explode('@', $identifier, 2), 2, '');
            return substr($local, 0, 2).'***@'.$domain;
        }
        if (strlen($identifier) <= 4) return str_repeat('*', strlen($identifier));
        return substr($identifier, 0, 2).'***'.substr($identifier, -2);
    }

    private static function severityName(int $severity): string
    {
        return match ($severity) {
            E_ERROR => 'E_ERROR', E_WARNING => 'E_WARNING', E_PARSE => 'E_PARSE', E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR', E_CORE_WARNING => 'E_CORE_WARNING', E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING', E_USER_ERROR => 'E_USER_ERROR', E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE', E_STRICT => 'E_STRICT', E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED', E_USER_DEPRECATED => 'E_USER_DEPRECATED', default => 'E_'.$severity,
        };
    }
}
