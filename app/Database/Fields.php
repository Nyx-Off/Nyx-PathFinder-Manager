<?php
namespace app\Database;
final class Fields {
 const MAP=[
 'items'=>['type'=>'VARCHAR(40)','quantity'=>'INTEGER','bulk'=>'DECIMAL(10,2)','level'=>'INTEGER','rarity'=>'VARCHAR(40)','price'=>'VARCHAR(100)','traits'=>'TEXT','equipped'=>'INTEGER','invested'=>'INTEGER','consumable'=>'INTEGER','charges'=>'INTEGER','description'=>'TEXT'],
 'spells'=>['spell_rank'=>'INTEGER','casting'=>'VARCHAR(40)','tradition'=>'VARCHAR(40)','actions'=>'INTEGER','traits'=>'TEXT','range'=>'VARCHAR(190)','target'=>'VARCHAR(190)','duration'=>'VARCHAR(190)','prepared'=>'INTEGER','used'=>'INTEGER','description'=>'TEXT'],
 'feats'=>['level'=>'INTEGER','category'=>'VARCHAR(40)','traits'=>'TEXT','prerequisites'=>'TEXT','description'=>'TEXT','source'=>'VARCHAR(190)'],
 'conditions'=>['value'=>'INTEGER'],
 'resources'=>['current'=>'INTEGER','max'=>'INTEGER','reset'=>'VARCHAR(40)','description'=>'TEXT'],
 'spell_slots'=>['spell_rank'=>'INTEGER','current'=>'INTEGER','max'=>'INTEGER','casting'=>'VARCHAR(100)'],
 'modifiers'=>['target'=>'VARCHAR(100)','type'=>'VARCHAR(40)','value'=>'INTEGER','active'=>'INTEGER','rounds'=>'INTEGER','duration'=>'VARCHAR(190)'],
 'notes'=>['category'=>'VARCHAR(40)','description'=>'TEXT'],
 'journal_entries'=>['date'=>'VARCHAR(30)','description'=>'TEXT'],
 'abilities'=>['category'=>'VARCHAR(40)','description'=>'TEXT','source'=>'VARCHAR(190)'],
 'actions'=>['category'=>'VARCHAR(40)','traits'=>'TEXT','description'=>'TEXT'],
 'currency_transactions'=>['delta'=>'INTEGER','balance'=>'INTEGER','reversed'=>'INTEGER'],
 ];
 public static function split(string $table,array $data): array {$columns=[];foreach(self::MAP[$table]??[] as $key=>$type){$columns[$key]=$data[$key]??null;unset($data[$key]);if(is_bool($columns[$key]))$columns[$key]=(int)$columns[$key];}$columns['data']=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);return $columns;}
 public static function hydrate(string $table,array $row): array {$d=json_decode($row['data'],true)??[];foreach(self::MAP[$table]??[] as $key=>$type)if(isset($row[$key]))$d[$key]=$type==='INTEGER'?(int)$row[$key]:($type==='DECIMAL(10,2)'?(float)$row[$key]:$row[$key]);return $d;}
}
