<?php
$code = (int)($_GET['code'] ?? 404);
if (!in_array($code, [403,404,500])) {
    $code = 404;
}http_response_code($code);
?><!doctype html><html lang="fr"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Erreur <?= $code ?></title><link rel="stylesheet" href="public/assets/css/app.css"><main class="auth"><div class="sigil">✧</div><h1><?= $code ?></h1><p><?= [403 => 'Cet espace est protégé.',404 => 'Cette page est introuvable.',500 => 'Le grimoire est momentanément indisponible.'][$code] ?></p><a href="index.php">Revenir au grimoire</a></main></html>
