<?php

require dirname(__DIR__) . '/app/bootstrap.php';
if (PHP_SAPI !== 'cli') {
    exit;
}
$email = $argv[1] ?? '';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage : php bin/user.php adresse@email (mot de passe lu sur stdin)\n");
    exit(1);
}
fwrite(STDERR, "Mot de passe (12 caractères minimum, entrée standard) : ");
$password = rtrim(fgets(STDIN), "\r\n");
if (strlen($password) < 12 || strlen($password) > 200) {
    fwrite(STDERR, "Longueur invalide\n");
    exit(1);
}
try {
    app\Database\Connection::get()->prepare('INSERT INTO users(email,password,created_at) VALUES (?,?,?)')->execute([$email,password_hash($password, PASSWORD_DEFAULT),date('c')]);
    echo "Compte créé.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Création impossible.\n");
    exit(1);
}
