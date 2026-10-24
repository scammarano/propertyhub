<?php
declare(strict_types=1);

final class DatabaseBackup
{
    public static function dump(PDO $pdo,string $path): array
    {
        $fh=@fopen($path,'wb'); if(!$fh) throw new RuntimeException('No fue posible crear database.sql.');
        $tables=[];$views=[];$rowsTotal=0;
        fwrite($fh,"-- 2CAM Property Hub database backup\n-- Generated ".date(DATE_ATOM)."\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
        $objects=$pdo->query("SELECT TABLE_NAME,TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_TYPE,TABLE_NAME")->fetchAll();
        foreach($objects as $obj){if(($obj['TABLE_TYPE']??'')==='BASE TABLE')$tables[]=(string)$obj['TABLE_NAME'];else $views[]=(string)$obj['TABLE_NAME'];}
        try{$pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');$pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');}catch(Throwable $e){}
        try{
            foreach($tables as $table){
                $q=self::qi($table);$create=$pdo->query("SHOW CREATE TABLE {$q}")->fetch();$createSql=(string)($create['Create Table']??array_values($create?:[])[1]??'');
                fwrite($fh,"DROP TABLE IF EXISTS {$q};\n{$createSql};\n");
                $cols=$pdo->query("SHOW COLUMNS FROM {$q}")->fetchAll();$binary=[];foreach($cols as $c){$type=strtolower((string)$c['Type']);if(str_contains($type,'blob')||str_contains($type,'binary'))$binary[(string)$c['Field']]=true;}
                $stmt=$pdo->query("SELECT * FROM {$q}");$batch=[];$count=0;$columnNames=null;
                while($row=$stmt->fetch(PDO::FETCH_ASSOC)){
                    if($columnNames===null)$columnNames=array_keys($row);
                    $values=[];foreach($row as $name=>$value)$values[]=self::sqlValue($pdo,$value,isset($binary[$name]));
                    $batch[]='('.implode(',',$values).')';$count++;$rowsTotal++;
                    if(count($batch)>=100){self::writeInsert($fh,$q,$columnNames,$batch);$batch=[];}
                }
                if($batch!==[])self::writeInsert($fh,$q,$columnNames??[],$batch);
                fwrite($fh,"\n");
            }
            if($pdo->inTransaction())$pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();fclose($fh);throw $e;}
        foreach($views as $view){
            $q=self::qi($view);$create=$pdo->query("SHOW CREATE VIEW {$q}")->fetch();$sql=(string)($create['Create View']??'');
            $sql=preg_replace('/DEFINER=`[^`]+`@`[^`]+`\s*/i','',$sql)??$sql;
            if($sql!=='')fwrite($fh,"DROP VIEW IF EXISTS {$q};\n{$sql};\n\n");
        }
        fwrite($fh,"SET FOREIGN_KEY_CHECKS=1;\n");fclose($fh);
        return ['tables'=>count($tables),'views'=>count($views),'rows'=>$rowsTotal,'sha256'=>hash_file('sha256',$path)?:'','bytes'=>(int)filesize($path)];
    }

    public static function restore(PDO $pdo,string $path): array
    {
        $sql=file_get_contents($path); if($sql===false) throw new RuntimeException('No fue posible leer database.sql.');
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        $current=$pdo->query("SELECT TABLE_NAME,TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()")->fetchAll();
        foreach($current as $obj){$q=self::qi((string)$obj['TABLE_NAME']);try{$pdo->exec((($obj['TABLE_TYPE']??'')==='VIEW'?'DROP VIEW IF EXISTS ':'DROP TABLE IF EXISTS ').$q);}catch(Throwable $e){}}
        $count=0;try{foreach(SqlStatementSplitter::split($sql) as $statement){$pdo->exec($statement);$count++;}}finally{try{$pdo->exec('SET FOREIGN_KEY_CHECKS=1');}catch(Throwable $e){}}
        return ['statements'=>$count];
    }

    private static function writeInsert($fh,string $table,array $columns,array $rows): void
    { if($columns===[]||$rows===[])return; $cols=implode(',',array_map([self::class,'qi'],$columns));fwrite($fh,"INSERT INTO {$table} ({$cols}) VALUES\n".implode(",\n",$rows).";\n"); }
    private static function sqlValue(PDO $pdo,mixed $value,bool $binary=false): string
    { if($value===null)return 'NULL'; if($binary)return '0x'.bin2hex((string)$value); return $pdo->quote((string)$value); }
    private static function qi(string $name): string{return '`'.str_replace('`','``',$name).'`';}
}
