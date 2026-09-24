<?php
require __DIR__.'/app/bootstrap.php';
try{
 if(empty($_SESSION['user_id']))throw new DomainException('Connexion requise.',401);
 $r=new app\Services\Repository(app\Database\Connection::get(),(int)$_SESSION['user_id']);$id=(int)($_GET['id']??0);$c=$r->owned($id);
 if($_SERVER['REQUEST_METHOD']==='POST'){
  $operationLock=fopen(ROOT.'/storage/operations.lock','c');if(!$operationLock||!flock($operationLock,LOCK_SH))throw new RuntimeException();
  if(!hash_equals($_SESSION['csrf'],$_POST['csrf']??''))throw new DomainException('Session expirée.',403);
  $f=$_FILES['portrait']??null;if(!$f||$f['error']!==UPLOAD_ERR_OK||$f['size']>3*1024*1024)throw new DomainException('Image requise, maximum 3 Mo.',422);
  $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);$extensions=['image/jpeg'=>['jpg','jpeg'],'image/png'=>['png'],'image/webp'=>['webp']];$extension=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
  if(!isset($extensions[$mime])||!in_array($extension,$extensions[$mime]))throw new DomainException('Format autorisé : JPEG, PNG ou WebP.',422);
  $size=getimagesize($f['tmp_name']);if(!$size||$size[0]*$size[1]>16000000)throw new DomainException('Image trop grande (16 mégapixels maximum).',422);
  $source=imagecreatefromstring(file_get_contents($f['tmp_name']));if(!$source)throw new DomainException('Image invalide.',422);$scale=min(1,800/max($size[0],$size[1]));$target=imagecreatetruecolor(max(1,(int)($size[0]*$scale)),max(1,(int)($size[1]*$scale)));imagecopyresampled($target,$source,0,0,0,0,imagesx($target),imagesy($target),$size[0],$size[1]);$name=bin2hex(random_bytes(24)).'.jpg';imagejpeg($target,ROOT.'/storage/uploads/'.$name,88);chmod(ROOT.'/storage/uploads/'.$name,0600);
  $r->query('UPDATE characters SET portrait=?,revision=revision+1 WHERE id=?',[$name,$id]);$r->audit($id,'Portrait modifié');if(preg_match('/^[a-f0-9]{48}\.jpg$/',$c['portrait'])&&is_file(ROOT.'/storage/uploads/'.$c['portrait']))unlink(ROOT.'/storage/uploads/'.$c['portrait']);header('Content-Type: application/json');echo json_encode(['ok'=>true]);exit;
 }
 if($_SERVER['REQUEST_METHOD']!=='GET')throw new DomainException('Méthode non autorisée.',405);
 if(!preg_match('/^[a-f0-9]{48}\.jpg$/',$c['portrait'])||!is_file(ROOT.'/storage/uploads/'.$c['portrait']))throw new DomainException('Portrait introuvable.',404);
 header('Content-Type: image/jpeg');readfile(ROOT.'/storage/uploads/'.$c['portrait']);
}catch(DomainException $e){http_response_code($e->getCode());header('Content-Type: application/json');echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);}catch(Throwable $e){http_response_code(500);header('Content-Type: application/json');echo json_encode(['ok'=>false,'error'=>'Impossible de traiter le portrait.']);}
