<?php

// Run before commits. Never print the configured values.
require dirname(__DIR__) . '/app/bootstrap.php';
$names = [];
$code = 0;
exec('git diff --cached --name-only', $names, $code);
if ($code) {
    exit(1);
}
foreach ($names as $name) {
    if ($name === '.env' || (str_starts_with($name, 'storage/') && $name !== 'storage/.htaccess')) {
        throw new RuntimeException('Private file staged');
    }
    $bytes = shell_exec('git show ' . escapeshellarg(':' . $name)) ?? '';
    foreach (['DB_PASS','SETUP_TOKEN_HASH'] as $key) {
        $value = env($key);
        if ($value !== '' && str_contains($bytes, $value)) {
            throw new RuntimeException('Configured secret found');
        }
    }
}
echo count($names) . " staged files checked\n";
