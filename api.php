<?php
require __DIR__.'/app/bootstrap.php';
use app\Database\Connection;
use app\Services\Repository;
use app\Services\CharacterService;
use app\Services\Validator;
header('Content-Type: application/json; charset=utf-8');
try {
 if(empty($_SESSION['user_id']))throw new DomainException('Connexion requise.',401);
 $r=new Repository(Connection::get(),(int)$_SESSION['user_id']);$service=new CharacterService($r);
 $method=$_SERVER['REQUEST_METHOD'];if($method!=='GET'){$operationLock=fopen(ROOT.'/storage/operations.lock','c');if(!$operationLock||!flock($operationLock,LOCK_SH))throw new RuntimeException();}$id=(int)($_GET['id']??0);$action=(string)($_GET['action']??'');
 if($method==='GET') {
  if($action==='export'){$data=$r->export($id);header('Content-Disposition: attachment; filename="personnage-'.$id.'.json"');}
  else $data=$id?$r->get($id):$r->all();
 }else {
  if(!hash_equals($_SESSION['csrf'],$_SERVER['HTTP_X_CSRF_TOKEN']??''))throw new DomainException('Session expirée. Rechargez la page.',403);
  if((int)($_SERVER['CONTENT_LENGTH']??0)>3*1024*1024)throw new DomainException('Requête trop volumineuse.',413);
  $raw=file_get_contents('php://input');$d=json_decode($raw,true,64,JSON_THROW_ON_ERROR);if(!is_array($d))throw new DomainException('Requête invalide.',422);
  if($method==='POST' && $action==='create'){$r->db->beginTransaction();$new=$r->create($d);$service->configureNew($new,$d);$r->db->commit();$data=$r->get($new);http_response_code(201);}
  elseif($method==='POST'&&$action==='import')$data=$r->get($service->import($d));
  elseif($method==='POST'&&$action==='duplicate')$data=$r->get($service->import($r->export($id)));
  elseif(($method==='DELETE'&&$action==='delete')||($method==='PATCH'&&$action!=='delete'))$data=$service->run($id,$action,$d);
  else throw new DomainException('Méthode non autorisée.',405);
 }
 echo json_encode($method==='GET'&&$action==='export'?$data:['ok'=>true,'data'=>$data],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}catch(DomainException $e){http_response_code(in_array($e->getCode(),[401,403,404,405,409,413,422])?$e->getCode():422);echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);}
catch(JsonException $e){http_response_code(422);echo json_encode(['ok'=>false,'error'=>'JSON invalide.']);}
catch(Throwable $e){if(isset($r)&&$r->db->inTransaction())$r->db->rollBack();error_log('API failure '.get_class($e).' code '.$e->getCode());http_response_code(500);echo json_encode(['ok'=>false,'error'=>'Erreur interne. Réessayez dans un instant.']);}
