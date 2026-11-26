<?php

declare(strict_types=1);

require dirname(__DIR__).'/src/RuntimePermissions.php';

use SymSvelCli\RuntimePermissions;

if (PHP_OS_FAMILY !== 'Windows') {
    throw new RuntimeException('Requires native Windows');
}
$root = $argv[1] ?? throw new RuntimeException('Fixture path required');
$account = $argv[2] ?? throw new RuntimeException('HTTP account required');
$mode = $argv[3] ?? 'apply';
try {
    $message = RuntimePermissions::prepare($root, $account);
    if ($mode !== 'apply' || !str_contains($message, 'ACL NTFS runtime')) {
        throw new LogicException('Unexpected success: '.$message);
    }
} catch (RuntimeException $error) {
    if ($mode === 'refuse-link' && str_contains($error->getMessage(), 'Link or reparse point refused')) {
        echo "Linked runtime file refused before ACL mutation.\n";
        exit(0);
    }
    throw $error;
}
echo $message."\n";
