<?php

declare(strict_types=1);

require dirname(__DIR__).'/src/Cli.php';
require dirname(__DIR__).'/src/RuntimePermissions.php';

use SymSvelCli\Cli;
use SymSvelCli\RuntimePermissions;

function check(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}

check(PHP_OS_FAMILY === 'Windows', 'Requires native Windows');
$available = new ReflectionMethod(Cli::class, 'available');
foreach (['composer', 'git', 'node', 'npm', 'python'] as $program) {
    check($available->invoke(null, $program), 'Missing executable: '.$program);
}
check(!$available->invoke(null, 'symsvel-not-an-executable-2304'), 'Missing executable must not be reported present');
$composer = new ReflectionMethod(Cli::class, 'windowsComposer');
$command = $composer->invoke(null);
check(is_array($command), 'Composer resolution');
$run = new ReflectionMethod(Cli::class, 'run');
$run->invoke(null, ['composer', '--version'], sys_get_temp_dir());

$root = sys_get_temp_dir().'/symsvel Windows '.bin2hex(random_bytes(5));
mkdir($root);
try {
    check(str_contains(RuntimePermissions::prepare($root), 'ACL NTFS inchangées'), 'Same-account path must not change ACLs');
    try {
        RuntimePermissions::prepare($root, 'other-account');
        throw new LogicException('Unqualified cross-account path must fail closed');
    } catch (RuntimeException $e) {
        check(str_contains($e->getMessage(), 'non qualifiées'), 'Wrong diagnostic');
    }
    mkdir($root.'/.git/objects', 0775, true);
    file_put_contents($root.'/.git/objects/test', 'test');
    $remove = new ReflectionMethod(Cli::class, 'removeUpstreamGit');
    $remove->invoke(null, $root.'/.git');
    check(!file_exists($root.'/.git'), 'Native Git cleanup failed');
} finally {
    if (is_dir($root)) {
        rmdir($root);
    }
}
echo "Windows CLI prerequisites and bootstrap primitives: OK\n";
