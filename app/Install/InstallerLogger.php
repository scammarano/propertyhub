<?php
declare(strict_types=1);

final class InstallerLogger
{
    public static function exception(string $root, Throwable $e, array $context = []): string
    {
        $incident = date('YmdHis').'-'.bin2hex(random_bytes(4));
        $dir = rtrim($root, '/\\').DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'logs';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $file = $dir.DIRECTORY_SEPARATOR.'property-hub-installer-'.date('Y-m-d').'.log';
        $versionMeta=json_decode((string)@file_get_contents(rtrim($root, '/\\').DIRECTORY_SEPARATOR.'VERSION.json'),true);
        $packageVersion=is_array($versionMeta)?trim((string)($versionMeta['version']??'')):'';
        $edgeContract=defined('InstallerService::EDGE_ENROLLMENT_CONTRACT') ? (string)InstallerService::EDGE_ENROLLMENT_CONTRACT : 'legacy-or-unknown';
        $record = [
            'timestamp'=>date(DATE_ATOM),
            'package_version'=>$packageVersion,
            'edge_enrollment_contract'=>$edgeContract,
            'incident_id'=>$incident,
            'event'=>'installer_exception',
            'step'=>(int)($context['step'] ?? 0),
            'action'=>(string)($context['action'] ?? ''),
            'exception'=>get_class($e),
            'message'=>self::redact($e->getMessage()),
            'file'=>$e->getFile(),
            'line'=>$e->getLine(),
            'trace'=>self::redact($e->getTraceAsString()),
            'php'=>PHP_VERSION,
            'sapi'=>PHP_SAPI,
        ];
        @file_put_contents($file, json_encode($record, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).PHP_EOL, FILE_APPEND|LOCK_EX);
        return $incident;
    }

    private static function redact(string $value): string
    {
        $patterns = [
            '/(central_registration_token|registration_token|access_token|api[_-]?key|password|secret)(\\s*[=:]\\s*)([^\\s&]+)/i',
            '/(Authorization:\\s*Bearer\\s+)[A-Za-z0-9._~+\\-\\/=]+/i',
        ];
        return preg_replace($patterns, '$1$2***', $value) ?? $value;
    }
}
