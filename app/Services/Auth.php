<?php

namespace app\Services;

use app\Database\Connection;

final class Auth
{
    public static function handle(): ?string
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return null;
        }
        if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
            return 'Session expirée. Rechargez la page.';
        }
        if (($_POST['action'] ?? '') === 'logout') {
            $_SESSION = [];
            session_regenerate_id(true);
            header('Location: index.php');
            exit;
        }
        $db = Connection::get();
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190 || strlen($password) < 12 || strlen($password) > 200) {
            return 'Adresse e-mail valide et mot de passe de 12 à 200 caractères requis.';
        }
        if (($_SESSION['auth_next'] ?? 0) > time()) {
            return 'Veuillez patienter avant une nouvelle tentative.';
        }
        $_SESSION['auth_next'] = time() + 3;
        $fingerprint = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . mb_strtolower($email));
        $now = time();
        $db->prepare('DELETE FROM login_attempts WHERE expires_at<?')->execute([$now]);
        $q = $db->prepare('SELECT attempts FROM login_attempts WHERE fingerprint=?');
        $q->execute([$fingerprint]);
        $attempts = $q->fetchColumn();
        if ($attempts !== false && (int)$attempts >= 10) {
            return 'Trop de tentatives. Réessayez dans quinze minutes.';
        }
        if ($attempts === false) {
            try {
                $db->prepare('INSERT INTO login_attempts VALUES (?,?,?)')->execute([$fingerprint,1,$now + 900]);
            } catch (\PDOException $e) {
                return 'Veuillez réessayer.';
            }
        } else {
            $db->prepare('UPDATE login_attempts SET attempts=attempts+1 WHERE fingerprint=?')->execute([$fingerprint]);
        }
        if (($_POST['action'] ?? '') === 'register') {
            if (\env('REGISTRATION', 'false') !== 'true') {
                return 'Les inscriptions sont désactivées. Créez le compte avec la commande serveur documentée.';
            }
            try {
                $db->prepare('INSERT INTO users(email,password,created_at) VALUES (?,?,?)')->execute([$email,password_hash($password, PASSWORD_DEFAULT),date('c')]);
            } catch (\PDOException $e) {
                return 'Impossible de créer ce compte.';
            }
        }
        $q = $db->prepare('SELECT * FROM users WHERE email=?');
        $q->execute([$email]);
        $user = $q->fetch();
        if (!$user || !password_verify($password, $user['password'])) {
            return 'Identifiants incorrects.';
        }
        $db->prepare('DELETE FROM login_attempts WHERE fingerprint=?')->execute([$fingerprint]);
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        header('Location: index.php');
        exit;
    }
}
