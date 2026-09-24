<?php
require dirname(__DIR__).'/app/bootstrap.php';
if(PHP_SAPI!=='cli')exit;
$db=app\Database\Connection::get();$stamp=date('Ymd-His').'-'.bin2hex(random_bytes(3));$base=ROOT.'/storage/backups/'.$stamp;mkdir($base,0700,true);
try {
 if($db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'){$q=$db->prepare('VACUUM INTO ?');$q->execute([$base.'/characters.sqlite']);}
 else {
  $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');$out=fopen($base.'/database.sql','xb');fwrite($out,"SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");
  foreach($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table){if(!preg_match('/^[a-z_]+$/',$table))throw new RuntimeException();$create=$db->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);fwrite($out,$create[1].";\n");$q=$db->query("SELECT * FROM `$table`");while($row=$q->fetch(PDO::FETCH_ASSOC))fwrite($out,"INSERT INTO `$table` (`".implode('`,`',array_keys($row))."`) VALUES (".implode(',',array_map(fn($v)=>$v===null?'NULL':$db->quote((string)$v),array_values($row))).");\n");}
  fwrite($out,"SET FOREIGN_KEY_CHECKS=1;\n");fclose($out);$db->commit();
 }
 $zip=new ZipArchive();$zip->open($base.'/uploads.zip',ZipArchive::CREATE);foreach(glob(ROOT.'/storage/uploads/*') as $file)if(is_file($file))$zip->addFile($file,basename($file));$zip->close();echo "Sauvegarde créée : storage/backups/$stamp\n";
}catch(Throwable $e){fwrite(STDERR,"Sauvegarde interrompue. Ne pas utiliser le dossier incomplet.\n");exit(1);}
