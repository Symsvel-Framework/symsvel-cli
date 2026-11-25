<?php

declare(strict_types=1);

require dirname(__DIR__).'/src/RuntimePermissions.php';

use SymSvelCli\RuntimePermissions;

function check(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}

function runAclTest(array $args): void
{
    $process = proc_open($args, [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes);
    check(is_resource($process) && proc_close($process) === 0, 'Failed: '.implode(' ', $args));
}

check(RuntimePermissions::workerIds(" 0 php-fpm php-fpm: master process\n 33 php-fpm8.4 php-fpm: pool www\n 33 /opt/php-fpm php-fpm: pool www\n 999 bash php-fpm: pool bogus\n 44 php-fpm php-fpm: pool other\n") === [33, 44], 'Worker parsing and deduplication');
check(RuntimePermissions::workerIds("0 php-fpm php-fpm: master process\n") === [], 'Master must not be selected');

// Real multi-user test on CI; no sudo or account creation in product code.
$http = getenv('SYMSVEL_ACL_TEST_HTTP_USER');
if ($http === false || $http === '') {
    echo "Worker parsing OK; real ACL test requires SYMSVEL_ACL_TEST_HTTP_USER.\n";
    exit(0);
}
$root = '/tmp/symsvel-acl-'.bin2hex(random_bytes(8));
mkdir($root, 0755);
chmod($root, 0755);
try {
    mkdir($root.'/var', 0755);
    mkdir($root.'/var/cache', 0755);
    mkdir($root.'/var/cache/dev', 0755);
    mkdir($root.'/var/cache/dev/twig', 0755);
    file_put_contents($root.'/var/cache/dev/twig/old.php', '<?php // old');
    file_put_contents($root.'/var/app.sqlite', 'private');
    chmod($root.'/var/app.sqlite', 0600);
    RuntimePermissions::prepare($root, $http);
    RuntimePermissions::prepare($root, $http);
    // Existing warmed cache and new directories created after initial ACLs.
    runAclTest(['sudo', '-n', '-u', $http, 'mkdir', $root.'/var/cache/dev/twig/7d']);
    runAclTest(['sudo', '-n', '-u', $http, 'touch', $root.'/var/cache/dev/twig/old.php']);
    runAclTest(['sudo', '-n', '-u', $http, 'sh', '-c', 'printf "HTTP" >> "$1"', 'sh', $root.'/var/cache/dev/twig/old.php']);
    check(str_ends_with((string) file_get_contents($root.'/var/cache/dev/twig/old.php'), 'HTTP'), 'HTTP must write existing cache');
    // Symfony/Twig mkdir requests 0777; Linux default ACLs are masked
    // by the requested group bits, not by umask when a default ACL exists.
    mkdir($root.'/var/cache/dev_new', 0777);
    runAclTest(['sudo', '-n', '-u', $http, 'mkdir', $root.'/var/cache/dev_new/twig']);
    runAclTest(['sudo', '-n', '-u', $http, 'touch', $root.'/var/log/dev.log']);
    check(file_put_contents($root.'/var/log/dev.log', "CLI\n", FILE_APPEND) !== false, 'CLI must write HTTP-created log');
    check(mkdir($root.'/var/cache/dev_new/twig/cli'), 'CLI must write HTTP-created directory');
    // Mimic Twig's temporary-file publication and cross-user invalidation.
    runAclTest(['sudo', '-n', '-u', $http, 'sh', '-c', 'p=$(mktemp "$1/cache.XXXXXX") && printf "compiled" > "$p" && mv "$p" "$1/compiled.php" && chmod 644 "$1/compiled.php"', 'sh', $root.'/var/cache/dev_new/twig']);
    check(file_get_contents($root.'/var/cache/dev_new/twig/compiled.php') === 'compiled', 'CLI must read HTTP-published cache');
    check(unlink($root.'/var/cache/dev_new/twig/compiled.php'), 'CLI must invalidate HTTP-published cache');
    if (PHP_OS_FAMILY === 'Linux') {
        mkdir($root.'/var/cache/restricted', 0755);
        runAclTest(['sudo', '-n', '-u', $http, 'test', '!', '-w', $root.'/var/cache/restricted']);
    }
    runAclTest(['sudo', '-n', '-u', $http, 'test', '!', '-w', $root.'/var/app.sqlite']);
    symlink($root.'/var/app.sqlite', $root.'/var/cache/outside');
    $refused = false;
    try {
        RuntimePermissions::prepare($root, $http);
    } catch (RuntimeException $e) {
        $refused = str_contains($e->getMessage(), 'Lien symbolique refusé');
    }
    check($refused, 'Symlink must be rejected before ACL mutation');
    unlink($root.'/var/cache/outside');
    runAclTest(['sudo', '-n', '-u', $http, 'test', '!', '-w', $root.'/var/app.sqlite']);
    echo "Real cross-user ACL test OK.\n";
} finally {
    runAclTest(['sudo', '-n', 'rm', '-rf', '--', $root]);
}
