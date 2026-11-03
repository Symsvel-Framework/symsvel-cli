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

    // Validate native manifest before invoking Composer.
    $manifest = $backend.'/config/symsvel-native-modules.json';
    mkdir(dirname($manifest), 0775, true);
    require_once dirname(__DIR__).'/src/Cli.php';
    $installNative = new ReflectionMethod(\SymSvelCli\Cli::class, 'installNativeModules');
    foreach ([
        [['schema' => 99, 'modules' => []], 'Version du manifeste'],
        [['schema' => 1, 'modules' => ['../../invalid']], 'Module natif invalide'],
    ] as [$payload, $expectedError]) {
        file_put_contents($manifest, json_encode($payload, JSON_THROW_ON_ERROR));
        try {
            $installNative->invoke(null, $backend, false);
            throw new RuntimeException('Invalid native manifest accepted');
        } catch (RuntimeException $error) {
            expect(str_contains($error->getMessage(), $expectedError), 'Native manifest validation failed');
        }
    }
    unlink($manifest);

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
