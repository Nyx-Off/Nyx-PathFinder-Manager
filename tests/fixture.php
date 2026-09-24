<?php

require dirname(__DIR__) . '/app/bootstrap.php';
if (PHP_SAPI !== 'cli') {
    exit;
}
$db = app\Database\Connection::get();
$path = ROOT . '/storage/test-credentials.json';
if (($argv[1] ?? '') === 'clean') {
    if (!is_file($path)) {
        exit;
    }
    foreach (json_decode(file_get_contents($path), true) as $u) {
        $q = $db->prepare('SELECT id FROM users WHERE email=?');
        $q->execute([$u['email']]);
        $id = $q->fetchColumn();
        if ($id) {
            $db->prepare('DELETE FROM characters WHERE user_id=?')->execute([$id]);
            $db->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
        }
    }
    unlink($path);
    echo "Fixtures nettoyées\n";
    exit;
}
if (is_file($path)) {
    echo "Fixtures existantes\n";
    exit;
}
$users = [];
for ($i = 0;$i < 2;$i++) {
    $email = 'test-' . bin2hex(random_bytes(12)) . '@example.invalid';
    $password = bin2hex(random_bytes(24));
    $db->prepare('INSERT INTO users(email,password,created_at) VALUES (?,?,?)')->execute([$email,password_hash($password, PASSWORD_DEFAULT),date('c')]);
    $users[] = ['email' => $email,'password' => $password];
}file_put_contents($path, json_encode($users));
chmod($path, 0600);
echo "Deux comptes temporaires créés\n";
