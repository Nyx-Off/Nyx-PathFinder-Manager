<?php
namespace app\Database;
use PDO;
final class Connection {
 public static function get(): PDO {
  static $db; if ($db) return $db;
  $driver=\env('DB_DRIVER','sqlite');
  $dsn=$driver==='mysql'?'mysql:host='.\env('DB_HOST').';port='.\env('DB_PORT','3306').';dbname='.trim(\env('DB_NAME')).';charset=utf8mb4':'sqlite:'.ROOT.'/storage/characters.sqlite';
  $db=new PDO($dsn,\env('DB_USER'),\env('DB_PASS'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
  if($driver==='sqlite') { $db->exec('PRAGMA foreign_keys=ON'); $db->exec('PRAGMA busy_timeout=5000'); }
  return $db;
 }
}
