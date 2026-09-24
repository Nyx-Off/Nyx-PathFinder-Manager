<?php

return function (PDO $db): void {
    if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
        return;
    }
    foreach (['schema_migrations', 'app_settings', 'login_attempts'] as $table) {
        $db->exec("ALTER TABLE `$table` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }
};
