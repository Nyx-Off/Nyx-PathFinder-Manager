<?php
require dirname(__DIR__).'/app/bootstrap.php';
if(PHP_SAPI!=='cli')exit;
$mode=$argv[1]??'--help';if(!in_array($mode,['--check','--copy'])){echo "Usage : php bin/migrate-storage.php --check | --copy\n--check teste MySQL sans modification.\n--copy exige une base MySQL vide, copie SQLite puis active MySQL dans .env. Mettre l'application en maintenance et sauvegarder avant copie.\n";exit;}
if(env('DB_PASS')===''){fwrite(STDERR,"DB_PASS n'est pas renseigné dans .env. SQLite reste actif.\n");exit(1);}
try{
 $mysql=new PDO('mysql:host='.env('DB_HOST').';port='.env('DB_PORT','3306').';dbname='.trim(env('DB_NAME')).';charset=utf8mb4',env('DB_USER'),env('DB_PASS'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
 if($mode==='--check'){echo "Connexion MySQL validée. Aucun changement de base active.\n";exit;}
 if(env('DB_DRIVER')!=='sqlite')throw new RuntimeException('source');
 if($mysql->query('SHOW TABLES')->fetch())throw new RuntimeException('nonempty');
 $source=app\Database\Connection::get();$source->beginTransaction();
 $mysql->exec('CREATE TABLE schema_migrations (version VARCHAR(190) PRIMARY KEY, applied_at VARCHAR(30) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
 foreach(glob(ROOT.'/database/migrations/*.php') as $file)(require $file)($mysql);
 $mysql->beginTransaction();
 foreach($source->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY rowid")->fetchAll(PDO::FETCH_COLUMN) as $table){
  if(!preg_match('/^[a-z_]+$/',$table))throw new RuntimeException('table');
  if(in_array($table,['app_settings','schema_migrations']))$mysql->exec("DELETE FROM `$table`");
  $query=$source->query("SELECT * FROM `$table`");$statement=null;
  while($row=$query->fetch()){$statement??=$mysql->prepare("INSERT INTO `$table` (`".implode('`,`',array_keys($row))."`) VALUES (".implode(',',array_fill(0,count($row),'?')).")");$statement->execute(array_values($row));}
  $src=(int)$source->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();$dst=(int)$mysql->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();if($src!==$dst)throw new RuntimeException('count');
 }
 $mysql->commit();$source->commit();
 $path=ROOT.'/.env';$env=file_get_contents($path);$env=preg_replace('/^DB_DRIVER=.*$/m','DB_DRIVER=mysql',$env);file_put_contents($path.'.next',$env,LOCK_EX);chmod($path.'.next',0600);rename($path.'.next',$path);echo "Copie vérifiée et MySQL activé. La base SQLite d'origine est conservée.\n";
}catch(Throwable $e){if(isset($mysql)&&$mysql->inTransaction())$mysql->rollBack();if(isset($source)&&$source->inTransaction())$source->rollBack();fwrite(STDERR,"Opération interrompue ; aucune activation MySQL. Vérifiez la connexion et que la destination est vide. Un échec après création du schéma peut laisser une destination initialisée : ne la supprimez pas sans contrôle.\n");exit(1);}
