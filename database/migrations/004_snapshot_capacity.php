<?php

return function (PDO $db): void {
    if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        foreach (['level_history','audit_history'] as $table) {
            $db->exec("ALTER TABLE `$table` MODIFY `data` LONGTEXT NOT NULL");
        }
    }
};
