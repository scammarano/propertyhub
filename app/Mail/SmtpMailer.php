<?php
declare(strict_types=1);

final class SmtpMailer
{
    public static function send(array $cfg,string $to,string $subject,string $html): void
    {
        $to=trim($to);if(!filter_var($to,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Correo destino inválido.');
        if(($cfg['transport']??'smtp')==='php_mail'){
            $from=(string)($cfg['from_email']??'');$name=(string)($cfg['from_name']??'Property Hub');
            $headers="MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: ".self::header($name)." <{$from}>\r\n";
            if(!mail($to,'=?UTF-8?B?'.base64_encode($subject).'?=',$html,$headers))throw new RuntimeException('PHP mail() no aceptó el mensaje.');return;
        }
        $host=trim((string)($cfg['smtp_host']??''));$port=(int)($cfg['smtp_port']??587);$security=(string)($cfg['smtp_security']??'tls');
        $user=(string)($cfg['smtp_username']??'');$pass=(string)($cfg['smtp_password']??'');$from=trim((string)($cfg['from_email']??$user));$fromName=(string)($cfg['from_name']??'Property Hub');
        if($host===''||$port<1||$from==='')throw new RuntimeException('SMTP incompleto: host, puerto y remitente son obligatorios.');
        $remote=($security==='ssl'?'ssl://':'').$host.':'.$port;$fp=@stream_socket_client($remote,$errno,$errstr,12,STREAM_CLIENT_CONNECT);
        if(!$fp)throw new RuntimeException('No fue posible conectar SMTP: '.$errstr.' ('.$errno.').');stream_set_timeout($fp,12);self::expect($fp,[220]);
        $ehlo=self::ehloName();self::cmd($fp,'EHLO '.$ehlo,[250]);
        if($security==='tls'){self::cmd($fp,'STARTTLS',[220]);if(!stream_socket_enable_crypto($fp,true,STREAM_CRYPTO_METHOD_TLS_CLIENT))throw new RuntimeException('SMTP no pudo activar TLS.');self::cmd($fp,'EHLO '.$ehlo,[250]);}
        if($user!==''){self::cmd($fp,'AUTH LOGIN',[334]);self::cmd($fp,base64_encode($user),[334]);self::cmd($fp,base64_encode($pass),[235]);}
        self::cmd($fp,'MAIL FROM:<'.$from.'>',[250]);self::cmd($fp,'RCPT TO:<'.$to.'>',[250,251]);self::cmd($fp,'DATA',[354]);
        $headers=['Date: '.date(DATE_RFC2822),'From: '.self::header($fromName).' <'.$from.'>','To: <'.$to.'>','Subject: =?UTF-8?B?'.base64_encode($subject).'?=','MIME-Version: 1.0','Content-Type: text/html; charset=UTF-8','Content-Transfer-Encoding: 8bit','Message-ID: <'.bin2hex(random_bytes(12)).'@'.$ehlo.'>'];
        $body=implode("\r\n",$headers)."\r\n\r\n".str_replace(["\r\n.\r\n","\n.\n"],["\r\n..\r\n","\n..\n"],$html)."\r\n.";self::cmd($fp,$body,[250]);self::cmd($fp,'QUIT',[221]);fclose($fp);
    }
    private static function cmd($fp,string $command,array $codes): string{fwrite($fp,$command."\r\n");return self::expect($fp,$codes);}
    private static function expect($fp,array $codes): string{$resp='';while(($line=fgets($fp,8192))!==false){$resp.=$line;if(strlen($line)>=4&&$line[3]===' ')break;}if($resp==='')throw new RuntimeException('SMTP cerró la conexión sin respuesta.');$code=(int)substr($resp,0,3);if(!in_array($code,$codes,true))throw new RuntimeException('SMTP '.$code.': '.trim($resp));return $resp;}
    private static function header(string $v): string{return '=?UTF-8?B?'.base64_encode($v).'?=';}
    private static function ehloName(): string{$h=preg_replace('/[^a-z0-9.-]/i','',gethostname()?:'propertyhub.local');return $h?:'propertyhub.local';}
}
