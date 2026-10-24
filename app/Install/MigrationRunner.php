<?php
declare(strict_types=1);

final class MigrationRunner
{
    public function __construct(private PDO $pdo)
    {
        // Dynamic SQL migrations can legitimately execute a SELECT as a no-op
        // (for example EXECUTE of a prepared "SELECT 1"). MySQL then leaves a
        // result set attached to the connection unless it is buffered/drained.
        // Force buffered queries when the driver supports it; executeStatement()
        // below also closes every cursor explicitly, so migrations remain safe on
        // hosts whose PDO defaults differ.
        try {
            if (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
                $this->pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
            }
        } catch (Throwable) { /* driver may not expose the attribute */ }
    }

    /** @param string[] $files */
    public function run(array $files): array
    {
        $results = [];
        $pendingRecords = [];
        $batch = 1;
        if ($this->tableExists('bh_schema_migrations')) {
            $batchStmt = $this->pdo->query('SELECT COALESCE(MAX(batch),0)+1 FROM bh_schema_migrations');
            $batch = (int)$batchStmt->fetchColumn();
            $batchStmt->closeCursor();
        }

        foreach ($files as $file) {
            if (!is_file($file)) throw new RuntimeException('Migración no encontrada: '.basename($file));
            $name = basename($file);
            $checksum = hash_file('sha256', $file) ?: '';
            if ($this->migrationExecuted($name, $checksum)) {
                $results[] = ['migration'=>$name,'status'=>'already_applied'];
                continue;
            }
            $sql = file_get_contents($file);
            if ($sql === false) throw new RuntimeException('No fue posible leer la migración '.$name);
            try {
                foreach (SqlStatementSplitter::split($sql) as $statement) $this->executeStatement($statement);
                $pendingRecords[] = [$name,$checksum];
                if ($this->tableExists('bh_schema_migrations')) {
                    foreach ($pendingRecords as [$pendingName,$pendingChecksum]) $this->record($pendingName,$pendingChecksum,$batch);
                    $pendingRecords=[];
                }
                $results[] = ['migration'=>$name,'status'=>'applied'];
            } catch (Throwable $e) {
                throw new RuntimeException('Falló la migración '.$name.': '.$e->getMessage(), 0, $e);
            }
        }
        if ($pendingRecords !== []) throw new RuntimeException('No se creó la tabla bh_schema_migrations; no es posible registrar las migraciones ejecutadas.');
        return $results;
    }

    private function executeStatement(string $statement): void
    {
        // PDO::exec() gives us no PDOStatement handle. If the SQL (directly or via
        // EXECUTE) produces a result set, there is then no cursor we can close and
        // the next statement fails with MySQL error 2014. query()+closeCursor()
        // works for DDL/DML as well and guarantees the connection is reusable.
        $stmt = $this->pdo->query($statement);
        if ($stmt instanceof PDOStatement) {
            try {
                // closeCursor() discards any unread rows/result sets and is the
                // documented PDO mechanism for releasing the connection.
                $stmt->closeCursor();
            } catch (Throwable $e) {
                throw new RuntimeException('No fue posible liberar el cursor SQL de la migración: '.$e->getMessage(), 0, $e);
            }
        }
    }

    private function record(string $name,string $checksum,int $batch): void
    {
        $stmt=$this->pdo->prepare('INSERT INTO bh_schema_migrations (migration,checksum_sha256,batch) VALUES (?,?,?) ON DUPLICATE KEY UPDATE checksum_sha256=VALUES(checksum_sha256),batch=VALUES(batch),executed_at=CURRENT_TIMESTAMP');
        $stmt->execute([$name,$checksum,$batch]);
        $stmt->closeCursor();
    }

    private function migrationExecuted(string $name, string $checksum): bool
    {
        if (!$this->tableExists('bh_schema_migrations')) return false;
        $stmt = $this->pdo->prepare('SELECT checksum_sha256 FROM bh_schema_migrations WHERE migration=?');
        $stmt->execute([$name]);
        $stored = $stmt->fetchColumn();
        $stmt->closeCursor();
        if ($stored === false) return false;
        if (!hash_equals((string)$stored, $checksum)) throw new RuntimeException('La migración '.$name.' ya fue ejecutada pero su checksum cambió.');
        return true;
    }

    private function tableExists(string $table): bool
    {
        // MariaDB/MySQL do not reliably accept placeholders in SHOW TABLES
        // when PDO native prepares are enabled. Query information_schema
        // instead, where the table name can be safely bound as a value.
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.tables '
            .'WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1'
        );
        $stmt->execute([$table]);
        $exists = $stmt->fetchColumn() !== false;
        $stmt->closeCursor();
        return $exists;
    }
}
