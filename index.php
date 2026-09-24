<?php
require __DIR__ . '/app/bootstrap.php';
try {
    $error = app\Services\Auth::handle();
} catch (Throwable $e) {
    http_response_code(500);
    $error = 'Service indisponible. Consultez les journaux du serveur.';
    error_log('Page failure ' . get_class($e));
}
?><!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="<?= e($_SESSION['csrf']) ?>"><title>Codex des héros — Pathfinder</title><link rel="stylesheet" href="public/assets/css/app.css"></head>
<body>
<?php if (empty($_SESSION['user_id'])): ?>
<main class="auth"><div class="sigil">✧</div><p class="eyebrow">NYX • PATHFINDER 2e / REMASTER</p><h1>Chaque héros<br>a son histoire.</h1><p class="muted">Votre grimoire de personnages, de la première aventure aux légendes.</p><form method="post" class="panel auth-form"><h2>Ouvrir le grimoire</h2><?php if ($error): ?><p role="alert" class="error"><?= e($error) ?></p><?php endif ?><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><label>Adresse e-mail<input name="email" type="email" autocomplete="username" required maxlength="190"></label><label>Mot de passe<input name="password" type="password" autocomplete="current-password" required minlength="12" maxlength="200"></label><button name="action" value="login" class="primary">Se connecter →</button><?php if (env('REGISTRATION') === 'true'): ?><button name="action" value="register">Créer un compte</button><?php endif ?><p class="muted small">Accès privé. Le premier compte se crée depuis le serveur avec <code>php bin/user.php</code>.</p></form></main>
<?php else: ?>
<aside class="sidebar"><a class="brand" href="index.php"><span class="sigil small-sigil">✧</span><span>CODEX<small>DES HÉROS</small></span></a><p class="eyebrow">VOTRE GRIMOIRE</p><button id="home" class="nav active">◈ Mes personnages</button><nav id="sheet-nav" aria-label="Fiche du personnage"></nav><div class="sidebar-bottom"><p>PATHFINDER 2e<br><span class="muted">Édition classique & Remaster</span></p><form method="post"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><button name="action" value="logout" class="quiet">Déconnexion</button></form></div></aside>
<div class="workspace"><header class="topbar"><span>Le grimoire des aventuriers</span><span id="save-status" role="status">Vos aventures, sauvegardées.</span></header><main id="app" tabindex="-1"><p>Ouverture du grimoire…</p></main><footer>Une fiche vivante, pour toutes vos aventures. • Outil indépendant, non affilié à Paizo.</footer></div>
<dialog id="modal" aria-labelledby="modal-title"><div class="dialog-head"><h2 id="modal-title"></h2><button id="close-modal" aria-label="Fermer">×</button></div><div id="modal-body"></div></dialog><div id="toast" role="status" aria-live="polite"></div><script type="module" src="public/assets/js/app.js"></script>
<?php endif ?>
</body></html>
