<?php

declare(strict_types=1);

function expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function executeCli(array $arguments, string $cwd): array
{
    $command = [PHP_BINARY, dirname(__DIR__).'/bin/symsvel', ...$arguments];
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd);
    expect(is_resource($process), 'Unable to start standalone CLI');
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [proc_close($process), (string) $stdout, (string) $stderr];
}

function removeTree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($path);
}

// Regression: the explicit post-install module sync must remain inside the
// SYMSVEL_DEFER_BUILDS scope, otherwise it starts all Svelte builds before setup.
$cliSource = (string) file_get_contents(dirname(__DIR__).'/src/Cli.php');
$deferStart = strpos($cliSource, "putenv('SYMSVEL_DEFER_BUILDS=1');");
$explicitSync = strpos($cliSource, "self::run([PHP_BINARY, 'scripts/symsvel-module-sync.php'], \$backend);", $deferStart ?: 0);
$deferRestore = strpos($cliSource, "putenv('SYMSVEL_DEFER_BUILDS');", $deferStart ?: 0);
expect($deferStart !== false && $explicitSync !== false && $deferRestore !== false && $deferStart < $explicitSync && $explicitSync < $deferRestore, 'Module sync must run before deferred-build environment is restored');

$temporary = sys_get_temp_dir().'/symsvel-cli-smoke-'.bin2hex(random_bytes(6));
mkdir($temporary, 0775, true);

try {
    [$status, $out] = executeCli(['--help'], $temporary);
    expect($status === 0 && str_contains($out, 'make:module'), 'Help must advertise make:module');
    expect(str_contains($out, '[--bootstrap]'), 'Help must advertise bootstrap require mode');

    [$status, , $error] = executeCli(['make:module'], $temporary);
    expect($status !== 0 && str_contains($error, 'Nom requis'), 'Missing module name must fail');

    // Framework workspace: standalone CLI invokes the ModuleMaker source in Tooling.
    $framework = $temporary.'/framework-workspace';
    $toolingModule = $framework.'/framework/tools/src/Module';
    mkdir($toolingModule, 0775, true);
    file_put_contents($toolingModule.'/ModuleMaker.php', <<<'PHP'
<?php
namespace SymSvel\Tooling\Module;
final class ModuleMaker
{
    public function create(string $workspace, string $name, string $vendor = 'symsvel-framework', string $scopes = 'back'): string
    {
        $destination = $workspace.'/modules/'.$name;
        mkdir($destination, 0775, true);
        file_put_contents($destination.'/invocation.json', json_encode([
            'vendor' => $vendor, 'scopes' => $scopes,
        ], JSON_THROW_ON_ERROR));
        return $destination;
    }
}
PHP);
    mkdir($framework.'/nested', 0775, true);
    [$status, $out, $error] = executeCli(['make:module', 'documents', '--scopes=back,front', '--vendor=acme'], $framework.'/nested');
    expect($status === 0, 'Framework ModuleMaker invocation failed: '.$error);
    expect(str_contains($out, $framework.'/modules/documents'), 'Framework destination mismatch');
    $invocation = json_decode((string) file_get_contents($framework.'/modules/documents/invocation.json'), true, 512, JSON_THROW_ON_ERROR);
    expect($invocation === ['vendor' => 'acme', 'scopes' => 'back,front'], 'Framework arguments mismatch');

    // Project workspace: the CLI delegates to the installed Symfony command.
    $project = $temporary.'/customer-project';
    $backend = $project.'/backend';
    mkdir($backend.'/bin', 0775, true);
    file_put_contents($backend.'/composer.json', json_encode([
        'require' => ['symsvel-framework/core' => '^1.0'],
    ], JSON_THROW_ON_ERROR));
    file_put_contents($backend.'/bin/console', <<<'PHP'
<?php
file_put_contents(__DIR__.'/../delegation.json', json_encode(array_slice($argv, 1), JSON_THROW_ON_ERROR));
PHP);
    [$status, , $error] = executeCli(['make:module', 'catalogue', '--vendor=acme', '--scopes=bo,app'], $project);
    expect($status === 0, 'Project Tooling delegation failed: '.$error);
    $delegated = json_decode((string) file_get_contents($backend.'/delegation.json'), true, 512, JSON_THROW_ON_ERROR);
    expect($delegated === [
        'symsvel:make:module',
        'catalogue',
        '--workspace='.$project,
        '--scopes=bo,app',
        '--vendor=acme',
        '--no-interaction',
    ], 'Delegated Symfony command arguments mismatch');

    [$status, , $error] = executeCli(['setup:finalize', '--unexpected'], $project);
    expect($status !== 0 && str_contains($error, 'ne prend pas'), 'Setup finalization must reject unexpected arguments');
    [$status, , $error] = executeCli(['setup:finalize'], $project);
    expect($status === 0, 'Setup finalization delegation failed: '.$error);
    $delegated = json_decode((string) file_get_contents($backend.'/delegation.json'), true, 512, JSON_THROW_ON_ERROR);
    expect($delegated === ['symsvel:setup:finalize', '--no-interaction'], 'Setup finalization delegation mismatch');

    // Validate native manifest before invoking Composer.
    $manifest = $backend.'/config/symsvel-native-modules.json';
    mkdir(dirname($manifest), 0775, true);
    require_once dirname(__DIR__).'/src/Cli.php';
    $installNative = new ReflectionMethod(\SymSvelCli\Cli::class, 'installNativeModules');
    // --dev must drop release-only Satis without removing unrelated repositories.
    $removeSatis = new ReflectionMethod(\SymSvelCli\Cli::class, 'removeSatisRepository');
    file_put_contents($backend.'/composer.json', json_encode([
        'require' => ['symsvel-framework/core' => '^1.0'],
        'repositories' => [
            ['type' => 'composer', 'url' => 'https://packages.xavier-tezza.fr/'],
            ['type' => 'composer', 'url' => 'https://third-party.example/repository'],
            ['type' => 'vcs', 'url' => 'https://github.com/Symsvel-Framework/symsvel-core.git'],
        ],
    ], JSON_THROW_ON_ERROR));
    $removeSatis->invoke(null, $backend);
    $repositories = json_decode((string) file_get_contents($backend.'/composer.json'), true, 512, JSON_THROW_ON_ERROR)['repositories'];
    expect(count($repositories) === 2, 'Dev repository cleanup must remove only Satis');
    expect($repositories[0]['url'] === 'https://third-party.example/repository', 'Third-party Composer repository must remain');
    expect($repositories[1]['type'] === 'vcs', 'GitHub VCS repository must remain');
    $removeSatis->invoke(null, $backend);
    expect(count(json_decode((string) file_get_contents($backend.'/composer.json'), true, 512, JSON_THROW_ON_ERROR)['repositories']) === 2, 'Dev repository cleanup must be idempotent');

    foreach ([
        [['schema' => 99, 'modules' => []], 'Version du manifeste'],
        [['schema' => 1, 'modules' => ['../../invalid']], 'Module natif invalide'],
    ] as [$payload, $expectedError]) {
        file_put_contents($manifest, json_encode($payload, JSON_THROW_ON_ERROR));
        try {
            $installNative->invoke(null, $backend, false, 'bo');
            throw new RuntimeException('Invalid native manifest accepted');
        } catch (RuntimeException $error) {
            expect(str_contains($error->getMessage(), $expectedError), 'Native manifest validation failed');
        }
    }
    // Confirm that a valid manifest delegates each native module to the
    // transaction launcher with the bootstrap flag, without running Composer.
    mkdir($backend.'/scripts', 0775, true);
    $launcher = $backend.'/scripts/symsvel-composer-transaction.php';
    file_put_contents($launcher, <<<'PHP'
<?php
file_put_contents(__DIR__.'/../native-invocation.json', json_encode(array_slice($argv, 1), JSON_THROW_ON_ERROR));
PHP);
    file_put_contents($manifest, json_encode([
        'schema' => 1,
        'modules' => ['symsvel-framework/module-roles'],
    ], JSON_THROW_ON_ERROR));
    $installNative->invoke(null, $backend, false, 'bo');
    $invocation = json_decode((string) file_get_contents($backend.'/native-invocation.json'), true, 512, JSON_THROW_ON_ERROR);
    expect($invocation === ['require', 'symsvel-framework/module-roles', '--bootstrap'], 'Native bootstrap invocation mismatch');
    unlink($backend.'/native-invocation.json');
    $beforeBootstrapRepositories = (string) file_get_contents($backend.'/composer.json');
    $installNative->invoke(null, $backend, true, 'bo');
    $invocation = json_decode((string) file_get_contents($backend.'/native-invocation.json'), true, 512, JSON_THROW_ON_ERROR);
    expect($invocation === ['require', 'symsvel-framework/module-roles:dev-main', '--bootstrap'], 'Native dev bootstrap invocation mismatch');
    expect(file_get_contents($backend.'/composer.json') === $beforeBootstrapRepositories, 'CLI bootstrap must not mutate Composer repositories outside Tooling transaction');
    unlink($backend.'/native-invocation.json');
    // All native modules must be sent in one Composer transaction.
    file_put_contents($manifest, json_encode([
        'schema' => 1,
        'modules' => ['symsvel-framework/module-admins', 'symsvel-framework/module-roles'],
    ], JSON_THROW_ON_ERROR));
    $installNative->invoke(null, $backend, true, 'bo');
    $invocation = json_decode((string) file_get_contents($backend.'/native-invocation.json'), true, 512, JSON_THROW_ON_ERROR);
    expect($invocation === ['require', 'symsvel-framework/module-admins:dev-main', 'symsvel-framework/module-roles:dev-main', '--bootstrap'], 'Native batch bootstrap invocation mismatch');
    unlink($backend.'/native-invocation.json');
    // App-specific modules must not be installed when App is absent.
    file_put_contents($manifest, json_encode([
        'schema' => 1,
        'modules' => [],
        'components' => ['app' => ['symsvel-framework/module-users']],
    ], JSON_THROW_ON_ERROR));
    $installNative->invoke(null, $backend, false, 'bo');
    expect(!is_file($backend.'/native-invocation.json'), 'Users must not install without App');
    $installNative->invoke(null, $backend, false, 'app');
    $invocation = json_decode((string) file_get_contents($backend.'/native-invocation.json'), true, 512, JSON_THROW_ON_ERROR);
    expect($invocation === ['require', 'symsvel-framework/module-users', '--bootstrap'], 'App must install Users');
    unlink($backend.'/native-invocation.json');
    unlink($launcher);
    rmdir($backend.'/scripts');
    unlink($manifest);

    // Every maintenance command must reject missing or invalid provenance.
    [$status, , $error] = executeCli(['update', 'app'], $project);
    expect($status !== 0 && str_contains($error, 'Canal SymSvel absent'), 'Component update must reject missing provenance');
    [$status, , $error] = executeCli(['update'], $project);
    expect($status !== 0 && str_contains($error, 'Canal SymSvel absent'), 'Composer update must reject missing provenance');
    $projectChannel = new ReflectionMethod(\SymSvelCli\Cli::class, 'persistProjectChannel');
    $projectChannel->invoke(null, $backend, 'dev');
    $channelFile = $backend.'/.symsvel/project.json';
    expect(json_decode((string) file_get_contents($channelFile), true)['channel'] === 'dev', 'Dev channel must persist');
    try {
        $projectChannel->invoke(null, $backend, 'stable');
        throw new RuntimeException('Channel switch must be refused');
    } catch (RuntimeException $error) {
        expect(str_contains($error->getMessage(), 'déjà présente'), 'Channel switch rejection mismatch');
    }
    file_put_contents($channelFile, '{"schema":1,"channel":"invalid"}');
    [$status, , $error] = executeCli(['update', 'app'], $project);
    expect($status !== 0 && str_contains($error, 'Canal SymSvel invalide'), 'Component update must reject invalid provenance');
    file_put_contents($channelFile, json_encode(['schema' => 1, 'channel' => 'dev'], JSON_THROW_ON_ERROR));

    // Package require automatically inherits the persisted development channel.
    mkdir($backend.'/scripts', 0775, true);
    file_put_contents($launcher, <<<'PHP'
<?php
file_put_contents(__DIR__.'/../package-invocation.json', json_encode(array_slice($argv, 1), JSON_THROW_ON_ERROR));
PHP);
    [$status, , $error] = executeCli(['require', 'symsvel-framework/module-demo'], $project);
    expect($status === 0, 'Dev package require failed: '.$error);
    $invocation = json_decode((string) file_get_contents($backend.'/package-invocation.json'), true, 512, JSON_THROW_ON_ERROR);
    expect($invocation === ['require', 'symsvel-framework/module-demo', '--dev'], 'Dev require must inherit project channel');
    [$status, , $error] = executeCli(['require', 'psr/log'], $project);
    expect($status === 0, 'Third-party package require failed: '.$error);
    $invocation = json_decode((string) file_get_contents($backend.'/package-invocation.json'), true, 512, JSON_THROW_ON_ERROR);
    expect($invocation === ['require', 'psr/log'], 'Third-party package must not inherit SymSvel dev-main');
    [$status, , $error] = executeCli(['require', 'symsvel-framework/module-demo:^1.0'], $project);
    expect($status === 0, 'Explicit package constraint failed: '.$error);
    $invocation = json_decode((string) file_get_contents($backend.'/package-invocation.json'), true, 512, JSON_THROW_ON_ERROR);
    expect($invocation === ['require', 'symsvel-framework/module-demo:^1.0'], 'Explicit constraint must remain unchanged by the CLI');
    [$status, , $error] = executeCli(['require', 'symsvel-framework/module-demo', '^1.0'], $project);
    expect($status === 0, 'Separate explicit constraint failed: '.$error);
    $invocation = json_decode((string) file_get_contents($backend.'/package-invocation.json'), true, 512, JSON_THROW_ON_ERROR);
    expect($invocation === ['require', 'symsvel-framework/module-demo', '^1.0'], 'Separate explicit constraint must not be overridden');
    [$status, , $error] = executeCli(['require', 'symsvel-framework/module-demo', '--no-interaction'], $project);
    expect($status === 0, 'Dev require with Composer option failed: '.$error);
    $invocation = json_decode((string) file_get_contents($backend.'/package-invocation.json'), true, 512, JSON_THROW_ON_ERROR);
    expect($invocation === ['require', 'symsvel-framework/module-demo', '--no-interaction', '--dev'], 'Composer option must not be mistaken for version constraint');
    [$status, , $error] = executeCli(['update', 'symsvel-framework/module-demo'], $project);
    expect($status === 0, 'Dev package update failed: '.$error);
    $invocation = json_decode((string) file_get_contents($backend.'/package-invocation.json'), true, 512, JSON_THROW_ON_ERROR);
    expect($invocation === ['update', 'symsvel-framework/module-demo'], 'Package update must preserve Composer lock constraints');
    [$status, , $error] = executeCli(['require', 'symsvel-framework/module-demo', '--dev'], $project);
    expect($status !== 0 && str_contains($error, 'réservé à create-project'), 'Per-command --dev must be rejected');
    unlink($backend.'/package-invocation.json');
    file_put_contents($channelFile, json_encode(['schema' => 1, 'channel' => 'stable'], JSON_THROW_ON_ERROR));
    [$status, , $error] = executeCli(['require', 'symsvel-framework/module-demo'], $project);
    expect($status === 0, 'Stable package require failed: '.$error);
    $invocation = json_decode((string) file_get_contents($backend.'/package-invocation.json'), true, 512, JSON_THROW_ON_ERROR);
    expect($invocation === ['require', 'symsvel-framework/module-demo'], 'Stable require must not select dev-main');
    [$status, , $error] = executeCli(['update', 'symsvel-framework/module-demo'], $project);
    expect($status === 0, 'Stable package update failed: '.$error);
    $invocation = json_decode((string) file_get_contents($backend.'/package-invocation.json'), true, 512, JSON_THROW_ON_ERROR);
    expect($invocation === ['update', 'symsvel-framework/module-demo'], 'Stable update must retain package target');
    [$status, , $error] = executeCli(['remove', 'symsvel-framework/module-demo'], $project);
    expect($status === 0, 'Stable package remove failed: '.$error);
    $invocation = json_decode((string) file_get_contents($backend.'/package-invocation.json'), true, 512, JSON_THROW_ON_ERROR);
    expect($invocation === ['remove', 'symsvel-framework/module-demo'], 'Remove must not rewrite package target');
    unlink($backend.'/package-invocation.json');
    unlink($launcher);
    rmdir($backend.'/scripts');
    file_put_contents($channelFile, json_encode(['schema' => 1, 'channel' => 'dev'], JSON_THROW_ON_ERROR));

    // Component updates must reach Tooling, never Composer's package transaction.
    [$status, , $error] = executeCli(['update', 'app'], $project);
    expect($status === 0, 'Component update delegation failed: '.$error);
    $delegated = json_decode((string) file_get_contents($backend.'/delegation.json'), true, 512, JSON_THROW_ON_ERROR);
    expect($delegated === [
        'symsvel:component:update',
        'app',
        '--no-interaction',
    ], 'Component update must delegate to Tooling');

    // --workspace must work even when invoked outside the selected project.
    unlink($backend.'/delegation.json');
    [$status, , $error] = executeCli(['make:module', 'explicit', '--workspace='.$project], $temporary);
    expect($status === 0, 'Explicit project workspace failed: '.$error);
    $delegated = json_decode((string) file_get_contents($backend.'/delegation.json'), true, 512, JSON_THROW_ON_ERROR);
    expect($delegated[1] === 'explicit' && $delegated[2] === '--workspace='.$project, 'Explicit workspace not used');

    echo "CLI smoke tests: OK\n";
} finally {
    removeTree($temporary);
}
