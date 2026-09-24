<?php
return function(PDO $db): void {
 $db->exec('CREATE TABLE app_settings (setting_key VARCHAR(100) PRIMARY KEY, setting_value TEXT NOT NULL)');
 $db->prepare('INSERT INTO app_settings VALUES (?,?)')->execute(['setup_complete','0']);
 $db->exec('CREATE TABLE login_attempts (fingerprint VARCHAR(64) PRIMARY KEY, attempts INTEGER NOT NULL, expires_at INTEGER NOT NULL)');
};
