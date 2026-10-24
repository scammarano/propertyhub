<?php
declare(strict_types=1);

final class InstallationMode
{
    public const CENTRAL = 'central_repository';
    public const NODE = 'building_node';
    public const EDGE = 'edge_agent';

    public static function roles(): array { return [self::CENTRAL,self::NODE,self::EDGE]; }

    /**
     * Resolve the local role from the installation lock first, then .env.
     *
     * The role is part of the installation identity and is not a runtime preference.
     * Updates preserve storage/installed.lock, so using it as the first source prevents
     * a stale/duplicated INSTANCE_ROLE environment value from making an Edge request
     * temporarily behave as a Property Node.
     */
    public static function current(): string
    {
        static $resolved = null;
        if (is_string($resolved)) return $resolved;

        $lockRole='';
        $lock=base_path('storage/installed.lock');
        if(is_file($lock)){
            $raw=@file_get_contents($lock);
            $json=is_string($raw)?json_decode($raw,true):null;
            if(is_array($json))$lockRole=(string)($json['instance_role']??'');
        }

        $envRole=(string)config('deployment.role', self::NODE);
        $resolved=self::normalize($lockRole!==''?$lockRole:$envRole);
        return $resolved;
    }

    public static function diagnostics(): array
    {
        $lockRole='';$lock=base_path('storage/installed.lock');
        if(is_file($lock)){
            $raw=@file_get_contents($lock);$json=is_string($raw)?json_decode($raw,true):null;
            if(is_array($json))$lockRole=(string)($json['instance_role']??'');
        }
        $envRole=(string)config('deployment.role', self::NODE);
        return ['resolved'=>self::current(),'installed_lock_role'=>$lockRole,'env_role'=>$envRole,'mismatch'=>$lockRole!==''&&self::normalize($lockRole)!==self::normalize($envRole)];
    }

    private static function normalize(string $raw): string
    {
        $raw=strtolower(trim($raw));
        return match($raw){
            'central','repository','central_repository'=>self::CENTRAL,
            'edge','agent','edge_agent','property_edge_agent','property edge agent'=>self::EDGE,
            'node','property_node','building','building_node','property','property node'=>self::NODE,
            default=>self::NODE,
        };
    }

    public static function isCentral(): bool { return self::current() === self::CENTRAL && (bool)config('deployment.repository_enabled', false); }
    public static function isNode(): bool { return self::current() === self::NODE; }
    public static function isEdge(): bool { return self::current() === self::EDGE; }
    public static function isManagedInstallation(): bool { return self::isNode() || self::isEdge(); }
    public static function label(): string
    {
        return match(self::current()) {
            self::CENTRAL => 'Property Hub Central',
            self::EDGE => 'Property Edge Agent',
            default => 'Property Node',
        };
    }
}
