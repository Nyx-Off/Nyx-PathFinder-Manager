<?php
return function(PDO $db): void {
 foreach(app\Database\Fields::MAP as $table=>$columns){
  $rows=$db->query("SELECT * FROM $table")->fetchAll();
  $mysql=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
  $existing=$mysql?array_column($db->query("SHOW COLUMNS FROM `$table`")->fetchAll(),'Field'):array_column($db->query("PRAGMA table_info($table)")->fetchAll(),'name');
  foreach($columns as $name=>$type)if(!in_array($name,$existing))$db->exec("ALTER TABLE $table ADD COLUMN `$name` $type NULL");
  foreach($rows as $r){$data=app\Database\Fields::split($table,app\Database\Fields::hydrate($table,$r));$sets=implode(',',array_map(fn($k)=>"`$k`=?",array_keys($data)));$db->prepare("UPDATE $table SET $sets WHERE id=?")->execute([...array_values($data),$r['id']]);}
 }
};
