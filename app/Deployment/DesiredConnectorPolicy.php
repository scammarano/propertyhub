<?php
declare(strict_types=1);

/**
 * Fuente única de verdad para los conectores deseados de una instalación.
 *
 * Reglas:
 * - metadata_json.desired_connectors, incluso vacío, es autoritativo cuando la clave existe;
 * - instalaciones históricas sin esa clave heredan una sola vez technology_json del plan;
 * - el fallback histórico se materializa inmediatamente en metadata_json para que UI,
 *   manifiestos Central -> Node y distribución Node -> Edge lean exactamente el mismo estado;
 * - latest_compatible NO fija una versión. Los valores legacy de catálogo (ej. Vimar 0.4.0)
 *   se eliminan del desired state para no confundir catálogo, política deseada y versión instalada.
 */
final class DesiredConnectorPolicy
{
    public static function canonical(string $code): string
    {
        $code=strtolower(trim($code));
        return match($code){
            'zkteco_biosecurity','zkbio','zkbiosecurity','zkteco-biosecurity'=>'zkteco',
            'vimar_view','vimar_byme','vimar-view','vimar-byme'=>'vimar',
            'video','rtsp','video_onvif','video-onvif'=>'onvif',
            default=>$code,
        };
    }

    /** @return array{items:list<array<string,mixed>>,codes:list<string>,source:string,materialized:bool,explicit:bool} */
    public static function resolve(PDO $pdo,array $installation,bool $materialize=true): array
    {
        $meta=json_decode((string)($installation['metadata_json']??''),true);
        if(!is_array($meta))$meta=[];
        $hasExplicit=array_key_exists('desired_connectors',$meta);
        $source=(string)($meta['desired_connectors_source']??($hasExplicit?'installation_metadata':'unset'));
        $materialized=false;
        $raw=$hasExplicit?(array)$meta['desired_connectors']:[];

        if(!$hasExplicit){
            $code=(string)($installation['installation_code']??'');
            if($code!==''){
                $q=$pdo->prepare('SELECT technology_json FROM bh_repo_installation_plans WHERE installation_code=? ORDER BY id DESC LIMIT 1');
                $q->execute([$code]);
                $planRaw=$q->fetchColumn();
                $tech=json_decode((string)$planRaw,true);
                if(is_array($tech)){
                    $raw=$tech;
                    $source='installation_plan_materialized';
                    if($materialize){
                        $items=self::normalize($pdo,$raw);
                        $meta['desired_connectors']=$items;
                        $meta['desired_connectors_updated_at']=date(DATE_ATOM);
                        $meta['desired_connectors_source']=$source;
                        if(isset($installation['id'])){
                            $pdo->prepare('UPDATE bh_repo_installations SET metadata_json=? WHERE id=?')->execute([
                                json_encode($meta,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
                                (int)$installation['id']
                            ]);
                            $materialized=true;
                        }
                        $raw=$items;
                    }
                }
            }
        }

        $items=self::normalize($pdo,$raw);

        // Self-heal materialized/legacy desired state. Older builds copied the catalog
        // version into desired_connectors even when the policy was latest_compatible.
        // That made one screen say e.g. Vimar 0.4.0 while the catalog correctly said
        // 0.5.1. Canonical desired state stores a version only when the policy needs one.
        $reconciled=false;
        if($materialize&&$hasExplicit&&isset($installation['id'])){
            $before=json_encode(array_values((array)$raw),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
            $after=json_encode($items,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
            if($before!==$after){
                $meta['desired_connectors']=$items;
                $meta['desired_connectors_reconciled_at']=date(DATE_ATOM);
                $meta['desired_connectors_reconciled_reason']='canonical_version_policy_semantics';
                $pdo->prepare('UPDATE bh_repo_installations SET metadata_json=? WHERE id=?')->execute([
                    json_encode($meta,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
                    (int)$installation['id']
                ]);
                $reconciled=true;
            }
        }

        $codes=[];
        foreach($items as $item)if(!empty($item['enabled']))$codes[]=(string)$item['code'];
        return [
            'items'=>$items,
            'codes'=>array_values(array_unique($codes)),
            'source'=>$source,
            'materialized'=>$materialized,
            'reconciled'=>$reconciled,
            'explicit'=>$hasExplicit||$materialized,
        ];
    }

    /** @param array<int|string,mixed> $raw @return list<array<string,mixed>> */
    public static function normalize(PDO $pdo,array $raw): array
    {
        $out=[];
        foreach($raw as $key=>$item){
            $code=is_array($item)?(string)($item['code']??(is_string($key)?$key:'')):(is_string($key)&&!is_int($key)?$key:(string)$item);
            $code=self::canonical($code);
            if($code===''||!preg_match('/^[a-z0-9_-]{2,80}$/',$code))continue;
            $enabled=is_array($item)?(!array_key_exists('enabled',$item)||!empty($item['enabled'])):true;
            $version=is_array($item)?trim((string)($item['version']??'')):'';
            $policy=is_array($item)?strtolower(trim((string)($item['version_policy']??''))):'';
            // Legacy desired_connectors carried the catalog version only as descriptive
            // metadata. A missing policy therefore means latest_compatible, NOT exact.
            if($policy==='')$policy='latest_compatible';
            if($policy==='pinned')$policy='exact';
            if(!in_array($policy,['latest_compatible','exact','minimum','range'],true))$policy='latest_compatible';
            $constraint=is_array($item)?trim((string)($item['version_constraint']??'')):'';
            $out[$code]=['code'=>$code,'enabled'=>$enabled,'version_policy'=>$policy];
            // Keep a version only when it is semantically part of the policy. Never fill
            // latest_compatible from bh_connector_definitions: that table is catalog data,
            // not installed state and not a desired-version pin.
            if(in_array($policy,['exact','minimum'],true)&&$version!=='')$out[$code]['version']=$version;
            if($policy==='range'&&$constraint!=='')$out[$code]['version_constraint']=$constraint;
        }
        return array_values($out);
    }


    /** True when a published connector version satisfies the desired-state version policy. */
    public static function matchesRelease(array $item,string $releaseVersion): bool
    {
        if(empty($item['enabled']))return false;
        $policy=strtolower(trim((string)($item['version_policy']??'latest_compatible')));
        $desired=trim((string)($item['version']??''));
        return match($policy){
            'exact' => $desired===''?true:version_compare($releaseVersion,$desired,'=='),
            'minimum' => $desired===''?true:version_compare($releaseVersion,$desired,'>='),
            'range' => self::satisfiesConstraint($releaseVersion,trim((string)($item['version_constraint']??''))),
            default => true,
        };
    }

    public static function isValidVersion(string $version): bool
    {
        return preg_match('/^[0-9A-Za-z][0-9A-Za-z._+-]{0,49}$/',trim($version))===1;
    }

    /** Validates the supported constraint grammar without evaluating a concrete version. */
    public static function isValidConstraint(string $constraint): bool
    {
        $constraint=trim($constraint);if($constraint==='')return false;
        $parts=preg_split('/\s*,\s*|\s+/', $constraint,-1,PREG_SPLIT_NO_EMPTY)?:[];if($parts===[])return false;
        foreach($parts as $part)if(!preg_match('/^(>=|<=|>|<|==|=)?\s*([0-9A-Za-z][0-9A-Za-z._+-]*)$/',(string)$part))return false;
        return true;
    }

    /** Supports simple SemVer constraints such as >=0.5.0,<0.7.0 or =1.2.3. */
    public static function satisfiesConstraint(string $version,string $constraint): bool
    {
        $constraint=trim($constraint);if($constraint==='')return true;
        if(!self::isValidConstraint($constraint))return false;
        $parts=preg_split('/\s*,\s*|\s+/', $constraint,-1,PREG_SPLIT_NO_EMPTY)?:[];
        foreach($parts as $part){
            preg_match('/^(>=|<=|>|<|==|=)?\s*([0-9A-Za-z][0-9A-Za-z._+-]*)$/',(string)$part,$m);
            $op=$m[1]!==''?$m[1]:'=';$target=$m[2];if($op==='=')$op='==';
            if(!version_compare($version,$target,$op))return false;
        }
        return true;
    }

    /** @param list<string> $codes @return list<array<string,mixed>> */
    public static function fromCodes(PDO $pdo,array $codes): array
    {
        return self::normalize($pdo,array_values($codes));
    }
}
