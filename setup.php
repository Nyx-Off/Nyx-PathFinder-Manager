<?php
require __DIR__.'/app/bootstrap.php';
$error=null;$done=false;
try{
 $db=app\Database\Connection::get();$allowed=(int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn()===0&&env('SETUP_TOKEN_HASH')!=='';
 if(!$allowed){http_response_code(403);throw new DomainException('L’initialisation est fermée. Connectez-vous avec votre compte.');}
 if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!hash_equals($_SESSION['csrf'],$_POST['csrf']??'')||!hash_equals(env('SETUP_TOKEN_HASH'),hash('sha256',$_POST['token']??'')))throw new DomainException('Clé d’initialisation invalide. Utilisez le lien privé fourni.');
  $email=trim($_POST['email']??'');$password=$_POST['password']??'';if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>190||strlen($password)<12||strlen($password)>200)throw new DomainException('Adresse valide et mot de passe de 12 à 200 caractères requis.');
  $db->beginTransaction();$q=$db->prepare("UPDATE app_settings SET setting_value='1' WHERE setting_key='setup_complete' AND setting_value='0'");$q->execute();if($q->rowCount()!==1)throw new DomainException('Initialisation déjà effectuée.');
  if((int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn()!==0)throw new DomainException('Un compte existe déjà.');
  $db->prepare('INSERT INTO users(email,password,created_at) VALUES (?,?,?)')->execute([$email,password_hash($password,PASSWORD_DEFAULT),date('c')]);$uid=(int)$db->lastInsertId();$db->commit();session_regenerate_id(true);$_SESSION['user_id']=$uid;$_SESSION['csrf']=bin2hex(random_bytes(32));header('Location: index.php');exit;
 }
}catch(DomainException $e){if(isset($db)&&$db->inTransaction())$db->rollBack();$error=$e->getMessage();}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();http_response_code(500);$error='Initialisation momentanément indisponible.';}
?><!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Initialiser le grimoire</title><link rel="stylesheet" href="public/assets/css/app.css"></head><body><main class="auth"><div class="sigil">✧</div><h1>Votre grimoire privé.</h1><p>Créez le compte propriétaire. Ce formulaire sera ensuite désactivé.</p><?php if($error): ?><p class="error" role="alert"><?= e($error) ?></p><?php endif ?><?php if($allowed??false): ?><form method="post" class="panel auth-form"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><label>Clé d’initialisation<input id="setup-token" name="token" type="password" autocomplete="off" required></label><label>Adresse e-mail<input type="email" name="email" autocomplete="username" required></label><label>Votre mot de passe<input name="password" type="password" autocomplete="new-password" minlength="12" maxlength="200" required></label><button class="primary">Créer mon compte</button></form><script src="public/assets/js/setup.js"></script><?php endif ?><a href="index.php">Connexion</a></main></body></html>
