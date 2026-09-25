<?php
function migrations(): array {
    return [
        '001_initial' => function(PDO $pdo): void {
            // Initial schema is created by installer. Marker only.
        },
    ];
}
function run_migrations(PDO $pdo): array {
    $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (id VARCHAR(100) PRIMARY KEY, applied_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $applied=$pdo->query('SELECT id FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
    $done=[];
    foreach(migrations() as $id=>$fn){
        if(in_array($id,$applied,true))continue;
        $pdo->beginTransaction();
        try{$fn($pdo);$pdo->prepare('INSERT INTO schema_migrations(id,applied_at) VALUES(?,NOW())')->execute([$id]);$pdo->commit();$done[]=$id;}
        catch(Throwable $e){$pdo->rollBack();throw $e;}
    }
    return $done;
}
