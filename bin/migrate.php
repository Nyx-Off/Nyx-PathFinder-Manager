<?php
require dirname(__DIR__).'/app/bootstrap.php';
$db=app\Database\Connection::get();
$db->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(190) PRIMARY KEY, applied_at VARCHAR(30) NOT NULL)');
foreach(glob(ROOT.'/database/migrations/*.php') as $file) {
 $version=basename($file); $q=$db->prepare('SELECT version FROM schema_migrations WHERE version=?'); $q->execute([$version]); if($q->fetch()) continue;
 (require $file)($db); $db->prepare('INSERT INTO schema_migrations VALUES (?,?)')->execute([$version,date('c')]); echo "$version applied\n";
}
