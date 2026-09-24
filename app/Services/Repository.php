<?php
namespace app\Services;
use PDO;
final class Repository {
 const COLLECTIONS=['items','spells','feats','conditions','resources','notes','journal_entries','modifiers','level_history','currency_transactions','audit_history','abilities','actions','spell_slots'];
 public function __construct(public PDO $db, public int $user) {}
 public function query(string $sql,array $args=[]): \PDOStatement { $q=$this->db->prepare($sql);$q->execute($args);return $q; }
 public function owned(int $id): array { $c=$this->query('SELECT * FROM characters WHERE id=? AND user_id=?',[$id,$this->user])->fetch(); if(!$c)throw new \DomainException('Personnage introuvable.',404);return $c; }
 public function all(): array {return $this->query('SELECT id,name,ancestry,class,level,hp,archived,campaign FROM characters WHERE user_id=? ORDER BY archived,name',[$this->user])->fetchAll();}
 public function get(int $id): array {
  $c=$this->owned($id); $c['details']=json_decode($c['details'],true)??[];
  foreach(['attributes'=>'character_attributes','skills'=>'character_skills'] as $key=>$table)$c[$key]=$this->query("SELECT * FROM $table WHERE character_id=?",[$id])->fetchAll();
  foreach(self::COLLECTIONS as $table) { $limit=$table==='audit_history'?' ORDER BY id DESC LIMIT 100':''; $c[$table]=$this->query("SELECT * FROM $table WHERE character_id=?$limit",[$id])->fetchAll();foreach($c[$table] as &$row)$row['data']=\app\Database\Fields::hydrate($table,$row);unset($row); }
  $c['copper']=(int)$this->query('SELECT copper FROM currencies WHERE character_id=?',[$id])->fetchColumn();
  $c['computed']=\app\Rules\Engine::calculate($c);return $c;
 }
 public function insert(string $table,array $values): int { $keys=array_keys($values);$this->query('INSERT INTO '.$table.' ('.implode(',',array_map(fn($k)=>'`'.$k.'`',$keys)).') VALUES ('.implode(',',array_fill(0,count($keys),'?')).')',array_values($values));return (int)$this->db->lastInsertId(); }
 public function entry(int $id,string $table,string $name,array $data): int {return $this->insert($table,array_merge(['character_id'=>$id,'name'=>$name,'created_at'=>date('c')],\app\Database\Fields::split($table,$data)));}
 public function updateEntry(string $table,int $entryId,array $data,?string $name=null): void {
  $values=\app\Database\Fields::split($table,$data);if($name!==null)$values['name']=$name;
  $sets=implode(',',array_map(fn($k)=>"`$k`=?",array_keys($values)));$this->query("UPDATE $table SET $sets WHERE id=?",[...array_values($values),$entryId]);
 }
 public function create(array $data): int {
  $name=Validator::text($data['name']??'',190,true);$level=Validator::integer($data['level']??1,1,20);
  $id=$this->insert('characters',['user_id'=>$this->user,'name'=>$name,'ancestry'=>Validator::text($data['ancestry']??'',100),'heritage'=>Validator::text($data['heritage']??'',100),'background'=>Validator::text($data['background']??'',100),'class'=>Validator::text($data['class']??'',100),'level'=>$level,'details'=>'{}','created_at'=>date('c')]);
  foreach(\app\Rules\Catalog::ATTRIBUTES as $key=>$label)$this->insert('character_attributes',['character_id'=>$id,'name'=>$key,'value'=>Validator::integer($data['attributes'][$key]??0,-5,10)]);
  foreach(\app\Rules\Catalog::SKILLS as $key=>$attr)$this->insert('character_skills',['character_id'=>$id,'name'=>$key,'attribute'=>$attr,'rank'=>in_array($key,['Armure','Attaque','Perception','Vigueur','Réflexes','Volonté','Classe','Sorts'])?1:0]);
  $this->insert('currencies',['character_id'=>$id]);$c=$this->get($id);$this->query('UPDATE characters SET hp=? WHERE id=?',[$c['computed']['hp_max'],$id]);$this->entry($id,'level_history','Création • niveau '.$level,['choices'=>$data]);return $id;
 }
 public function audit(int $id,string $name,array $data=[]): void {$this->entry($id,'audit_history',$name,$data);}
 public function export(int $id): array {$c=$this->get($id);unset($c['user_id'],$c['computed'],$c['audit_history'],$c['portrait']);foreach($c['level_history'] as &$history)unset($history['data']['before']);unset($history);return ['format'=>'pf2-character','version'=>1,'character'=>$c];}
}
