<?php
declare(strict_types=1);

final class BackupCrypto
{
    private const MAGIC = "PHBACKUP1\n";
    private const CHUNK_SIZE = 1048576;
    private const ITERATIONS = 210000;

    public static function encryptFile(string $plainPath, string $encryptedPath, string $recoveryKey, array $metadata=[]): array
    {
        if (!extension_loaded('openssl')) throw new RuntimeException('OpenSSL es obligatorio para crear respaldos cifrados.');
        if ($recoveryKey==='') throw new InvalidArgumentException('La clave de recuperación está vacía.');
        $in=@fopen($plainPath,'rb'); if(!$in) throw new RuntimeException('No fue posible abrir el archivo temporal de respaldo.');
        $out=@fopen($encryptedPath,'wb'); if(!$out){fclose($in);throw new RuntimeException('No fue posible crear el archivo .phbackup.');}
        $salt=random_bytes(16);
        $key=hash_pbkdf2('sha256',$recoveryKey,$salt,self::ITERATIONS,32,true);
        $header=[
            'format'=>'PHBACKUP','version'=>1,'cipher'=>'aes-256-gcm','kdf'=>'pbkdf2-sha256',
            'iterations'=>self::ITERATIONS,'salt'=>base64_encode($salt),'chunk_size'=>self::CHUNK_SIZE,
            'created_at'=>date(DATE_ATOM),'plain_size'=>(int)filesize($plainPath),
            'metadata'=>$metadata,
        ];
        fwrite($out,self::MAGIC);
        fwrite($out,json_encode($header,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n");
        $seq=0;$plainHash=hash_init('sha256');
        try{
            while(!feof($in)){
                $chunk=fread($in,self::CHUNK_SIZE);
                if($chunk===false) throw new RuntimeException('Error leyendo el respaldo temporal.');
                if($chunk==='') break;
                hash_update($plainHash,$chunk);
                $nonce=random_bytes(12);$tag='';$aad='PHBACKUP1:'.$seq;
                $cipher=openssl_encrypt($chunk,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$nonce,$tag,$aad,16);
                if($cipher===false) throw new RuntimeException('OpenSSL no pudo cifrar un bloque del respaldo.');
                fwrite($out,pack('N',strlen($cipher)).$nonce.$tag.$cipher);
                $seq++;
            }
        } finally { fclose($in); fclose($out); }
        return ['chunks'=>$seq,'plain_sha256'=>hash_final($plainHash),'encrypted_sha256'=>hash_file('sha256',$encryptedPath)?:'','size'=>(int)filesize($encryptedPath),'header'=>$header];
    }

    public static function decryptFile(string $encryptedPath, string $plainPath, string $recoveryKey): array
    {
        if (!extension_loaded('openssl')) throw new RuntimeException('OpenSSL es obligatorio para restaurar respaldos cifrados.');
        $in=@fopen($encryptedPath,'rb'); if(!$in) throw new RuntimeException('No fue posible abrir el archivo .phbackup.');
        $magic=fgets($in); if($magic!==self::MAGIC){fclose($in);throw new RuntimeException('El archivo no es un respaldo Property Hub válido.');}
        $headerLine=fgets($in);$header=json_decode((string)$headerLine,true);
        if(!is_array($header)||($header['format']??'')!=='PHBACKUP'||(int)($header['version']??0)!==1){fclose($in);throw new RuntimeException('Formato de respaldo no soportado.');}
        $salt=base64_decode((string)($header['salt']??''),true); if($salt===false) {fclose($in);throw new RuntimeException('Cabecera de cifrado inválida.');}
        $iterations=max(100000,(int)($header['iterations']??self::ITERATIONS));
        $key=hash_pbkdf2('sha256',$recoveryKey,$salt,$iterations,32,true);
        $out=@fopen($plainPath,'wb'); if(!$out){fclose($in);throw new RuntimeException('No fue posible crear el archivo temporal de restauración.');}
        $seq=0;$plainHash=hash_init('sha256');
        try{
            while(!feof($in)){
                $lenRaw=fread($in,4); if($lenRaw===''||$lenRaw===false) break; if(strlen($lenRaw)!==4) throw new RuntimeException('Respaldo truncado.');
                $len=(int)(unpack('Nlen',$lenRaw)['len']??0); if($len<1||$len>self::CHUNK_SIZE+64) throw new RuntimeException('Bloque de respaldo inválido.');
                $nonce=self::readExact($in,12);$tag=self::readExact($in,16);$cipher=self::readExact($in,$len);
                $plain=openssl_decrypt($cipher,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$nonce,$tag,'PHBACKUP1:'.$seq);
                if($plain===false) throw new RuntimeException('La clave de recuperación es incorrecta o el respaldo está dañado.');
                hash_update($plainHash,$plain);fwrite($out,$plain);$seq++;
            }
        } finally { fclose($in); fclose($out); }
        return ['chunks'=>$seq,'plain_sha256'=>hash_final($plainHash),'header'=>$header];
    }

    public static function header(string $path): array
    {
        $fp=@fopen($path,'rb'); if(!$fp) throw new RuntimeException('No fue posible abrir el respaldo.');
        try{ if(fgets($fp)!==self::MAGIC) throw new RuntimeException('Archivo .phbackup inválido.'); $line=fgets($fp); $data=json_decode((string)$line,true); return is_array($data)?$data:[]; }
        finally{fclose($fp);}    
    }

    private static function readExact($stream,int $length): string
    {
        $data=''; while(strlen($data)<$length){$part=fread($stream,$length-strlen($data));if($part===false||$part==='')throw new RuntimeException('Respaldo truncado durante la lectura.');$data.=$part;}return $data;
    }
}
