<?php
declare(strict_types=1);

/**
 * Rebuilds the distributable Node/Edge Core from an installed Central tree.
 * This is a recovery path: the normal release still carries the Core under
 * resources/releases/, but Central can self-heal if a deployment mechanism
 * omitted that nested ZIP.
 */
final class CoreDistributionArtifactBuilder
{
    public function build(string $version,string $destination): array
    {
        if(!class_exists('ZipArchive'))throw new RuntimeException('La extensión ZIP de PHP es necesaria para reconstruir el Core distribuible.');
        $version=trim($version);if($version==='')throw new InvalidArgumentException('Versión vacía para reconstruir el Core.');
        $root=base_path();$dir=dirname($destination);
        if(!is_dir($dir)&&!@mkdir($dir,0775,true)&&!is_dir($dir))throw new RuntimeException('No se pudo crear resources/releases para reconstruir el Core.');
        $tmp=$destination.'.building.'.bin2hex(random_bytes(4));
        $zip=new ZipArchive();if($zip->open($tmp,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('No se pudo iniciar el ZIP reconstruido del Core.');
        $count=0;
        try{
            $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
            foreach($it as $file){
                if(!$file->isFile())continue;
                $relative=str_replace('\\','/',substr($file->getPathname(),strlen($root)+1));
                if($this->excluded($relative))continue;
                if($relative==='update-package.json'){
                    $raw=@file_get_contents($file->getPathname());$meta=is_string($raw)?json_decode($raw,true):null;
                    if(!is_array($meta))throw new RuntimeException('El update-package.json instalado no es válido; no se puede reconstruir el Core.');
                    $meta['schema']='2cam-property-update-v1';$meta['component_type']='core';$meta['component_code']='property-hub-core';$meta['display_name']='2CAM Property Hub Core';$meta['version']=$version;$meta['target_roles']=['building_node','edge_agent'];
                    // El Core distribuible no contiene el vault histórico resources/releases/**.
                    // Por tanto esos paths tampoco pueden ser health_files del artefacto Node/Edge.
                    $meta['health_files']=array_values(array_filter((array)($meta['health_files']??[]),static fn(mixed $path): bool=>is_string($path)&&!str_starts_with(strtolower(ltrim(str_replace('\\','/',trim($path)),'/')),'resources/releases/')));
                    $meta['name']='property-hub-core-'.$version;
                    $meta['notes']='Core distribuible reconstruido automáticamente por Property Hub Central '.$version.'.';
                    $zip->addFromString($relative,json_encode($meta,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));$count++;continue;
                }
                if(!$zip->addFile($file->getPathname(),$relative))throw new RuntimeException('No se pudo agregar '.$relative.' al Core reconstruido.');
                $count++;
            }
        }finally{$zip->close();}
        if($count<20||!is_file($tmp)||filesize($tmp)<=0){@unlink($tmp);throw new RuntimeException('La reconstrucción del Core produjo un paquete incompleto.');}
        if(is_file($destination)&&!@unlink($destination)){@unlink($tmp);throw new RuntimeException('No se pudo reemplazar el Core distribuible anterior.');}
        if(!@rename($tmp,$destination)){@unlink($tmp);throw new RuntimeException('No se pudo activar el Core distribuible reconstruido.');}
        @chmod($destination,0640);
        return ['path'=>$destination,'files'=>$count,'sha256'=>(string)(hash_file('sha256',$destination)?:'')];
    }

    private function excluded(string $relative): bool
    {
        $p=strtolower(ltrim(str_replace('\\','/',$relative),'/'));
        if($p==='.env'||str_starts_with($p,'.env.'))return true;
        if(str_starts_with($p,'storage/'))return true;
        if(str_starts_with($p,'resources/releases/'))return true;
        if(str_starts_with($p,'.git/'))return true;
        if($p==='release_validation_report.txt')return true;
        return false;
    }
}
