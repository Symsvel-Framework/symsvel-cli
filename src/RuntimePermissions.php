<?php

declare(strict_types=1);

namespace SymSvelCli;

/** Local runtime ACLs only; available before Composer installs Tooling. */
final class RuntimePermissions
{
    /** @return list<int> */
    public static function workerIds(string $processes): array
    {
        $ids = [];
        foreach (preg_split('/\R/', $processes) ?: [] as $line) {
            if (preg_match('~^\s*(\d+)\s+php-fpm(?:[0-9.]+)?: pool\s+\S.*$~D', $line, $match)
                && (int) $match[1] > 0) {
                $ids[] = (int) $match[1];
            }
        }

        return array_values(array_unique($ids));
    }

    public static function prepare(string $backend, ?string $httpUser = null): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return self::prepareWindows($backend, $httpUser);
        }
        if (!in_array(PHP_OS_FAMILY, ['Darwin', 'Linux'], true)
            || !function_exists('posix_geteuid') || !function_exists('posix_getpwuid')
            || !function_exists('posix_getpwnam')) {
            throw new \RuntimeException('ACL locales non prises en charge sur ce système (macOS/Linux et extension POSIX requis).');
        }

        $uid = posix_geteuid();
        if ($uid === 0) {
            throw new \RuntimeException('Exécutez la CLI avec votre compte de développement, pas root.');
        }
        $cli = posix_getpwuid($uid);
        if ($cli === false) {
            throw new \RuntimeException('Utilisateur CLI introuvable.');
        }
        if ($httpUser === null) {
            $configured = getenv('SYMSVEL_HTTP_USER');
            if ($configured !== false && $configured !== '') {
                $httpUser = $configured;
            }
        }
        if ($httpUser !== null) {
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_.-]*[$]?$/D', $httpUser)) {
                throw new \RuntimeException('Nom de compte HTTP invalide.');
            }
            $http = posix_getpwnam($httpUser);
            if ($http === false || $http['uid'] <= 0) {
                throw new \RuntimeException('Compte HTTP inexistant ou privilégié : '.$httpUser);
            }
        } else {
            // Darwin comm is a truncated process title, not a reliable executable name.
            // Request only effective UID and full, untruncated arguments on both systems.
            $ids = self::workerIds(self::command(['ps', '-axww', '-o', 'uid=', '-o', 'args=']));
            if ($ids === []) {
                return 'Aucun worker PHP-FPM détecté ; si le serveur HTTP utilise un autre compte, exécutez symsvel permissions:fix --http-user=COMPTE.';
            }
            if (count($ids) !== 1) {
                throw new \RuntimeException('Plusieurs comptes PHP-FPM détectés ; précisez le compte du projet avec --http-user=COMPTE.');
            }
            $http = posix_getpwuid($ids[0]);
            if ($http === false) {
                throw new \RuntimeException('Compte PHP-FPM introuvable.');
            }
        }
        if ($http['uid'] === $uid) {
            return 'CLI et PHP HTTP : même utilisateur, aucune ACL supplémentaire.';
        }

        // Validate the complete scope before changing any ACL. Do not follow
        // symlinks or modify hard-linked files, databases or private metadata.
        if (is_link($backend) || !is_dir($backend)) {
            throw new \RuntimeException('Backend non fiable.');
        }
        $var = $backend.'/var';
        self::ensureDirectory($var);
        $directories = [];
        $files = [];
        foreach (['cache', 'log'] as $name) {
            $root = $var.'/'.$name;
            self::ensureDirectory($root);
            $directories[] = $root;
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST,
            );
            foreach ($iterator as $item) {
                if ($item->isLink()) {
                    throw new \RuntimeException('Lien symbolique refusé : '.$item->getPathname());
                }
                if ($item->isDir()) {
                    $directories[] = $item->getPathname();
                } elseif ($item->isFile() && stat($item->getPathname())['nlink'] === 1) {
                    $files[] = $item->getPathname();
                } else {
                    throw new \RuntimeException('Fichier runtime non fiable : '.$item->getPathname());
                }
            }
        }

        // Each parent retains its inheritance ACL through Symfony cache:clear
        // (which creates a sibling of the environment cache under var/cache).
        foreach ([$cli, $http] as $account) {
            if (PHP_OS_FAMILY === 'Darwin') {
                $principal = 'user:'.$account['name'].' allow read,write,append,delete,writeattr';
                foreach (array_chunk($directories, 64) as $chunk) {
                    self::command(['chmod', '+a', $principal.',file_inherit,directory_inherit', ...$chunk]);
                    // Search and deletion of children are directory rights only.
                    self::command(['chmod', '+a', 'user:'.$account['name'].' allow search,delete_child,directory_inherit', ...$chunk]);
                }
                foreach (array_chunk($files, 64) as $chunk) {
                    self::command(['chmod', '+a', $principal, ...$chunk]);
                }
            } else {
                $acl = 'u:'.$account['uid'].':';
                foreach (array_chunk($directories, 64) as $chunk) {
                    self::command(['setfacl', '-m', $acl.'rwx,d:'.$acl.'rwx', '--', ...$chunk]);
                }
                foreach (array_chunk($files, 64) as $chunk) {
                    self::command(['setfacl', '-m', $acl.'rw-', '--', ...$chunk]);
                }
            }
        }

        return 'ACL runtime CLI/HTTP : '.$cli['name'].' / '.$http['name'].' (var/cache et var/log).';
    }

    /** Windows: explicit account, scoped NTFS ACLs; no automatic service guessing. */
    private static function prepareWindows(string $backend, ?string $httpUser): string
    {
        if (is_link($backend) || !is_dir($backend) || !is_writable($backend)) {
            throw new \RuntimeException('Backend Windows non fiable ou non accessible en écriture.');
        }
        $configured = getenv('SYMSVEL_HTTP_USER');
        $httpUser ??= $configured !== false && $configured !== '' ? $configured : null;
        if ($httpUser === null) {
            return 'Windows : Setup sous le compte CLI, ACL NTFS inchangées. Pour un compte HTTP distinct, utilisez permissions:fix --http-user=COMPTE.';
        }
        if (trim($httpUser) === '' || preg_match('/[\x00-\x1F\x7F]/', $httpUser)) {
            throw new \RuntimeException('Compte HTTP Windows invalide.');
        }
        $windows = getenv('SystemRoot');
        $powershell = $windows === false ? '' : $windows.'/System32/WindowsPowerShell/v1.0/powershell.exe';
        if (!is_file($powershell)) {
            throw new \RuntimeException('Windows PowerShell natif introuvable.');
        }
        // Constant script: the two user-controlled values are environment
        // variables, never interpolated in a PowerShell or cmd command line.
        $script = <<<'POWERSHELL'
$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'
# A parent PowerShell 7 may export incompatible module search paths.
# Load only Windows PowerShell's native, system-owned modules.
$env:PSModulePath = Join-Path $PSHOME 'Modules'
Import-Module (Join-Path $PSHOME 'Modules/Microsoft.PowerShell.Security/Microsoft.PowerShell.Security.psd1') -ErrorAction Stop
$backend = $env:SYMSVEL_ACL_BACKEND
$account = $env:SYMSVEL_ACL_HTTP_ACCOUNT
$cli = [System.Security.Principal.WindowsIdentity]::GetCurrent().User
try {
    $http = ([System.Security.Principal.NTAccount]::new($account)).Translate([System.Security.Principal.SecurityIdentifier])
} catch { throw 'Invalid or unknown HTTP account.' }
if ($cli.Equals($http)) { Write-Output 'same-account'; exit 0 }
# A real user, not Everyone, an alias, a service group or a privileged system identity.
$sidText = $http.Value
$user = @(Get-CimInstance -ClassName Win32_UserAccount -Filter "SID='$sidText'")
if ($user.Count -ne 1 -or $user[0].Disabled -or $sidText -match '-500$') {
    throw 'Invalid HTTP account: select an enabled, non-system Windows user.'
}
function CheckedItem([string]$path) {
    $item = Get-Item -LiteralPath $path -Force
    if (($item.Attributes -band [IO.FileAttributes]::ReparsePoint) -or $item.LinkType) {
        throw ('Link or reparse point refused: ' + $path)
    }
    return $item
}
function CheckedDirectory([string]$path) {
    if (-not [IO.Directory]::Exists($path)) {
        [void][IO.Directory]::CreateDirectory($path)
    }
    $item = CheckedItem $path
    if (-not $item.PSIsContainer) { throw ('Not a directory: ' + $path) }
    return $item
}
$entries = [System.Collections.Generic.List[object]]::new()
function Scan($item) {
    $acl = Get-Acl -LiteralPath $item.FullName
    $entries.Add([pscustomobject]@{ Item = $item; Original = $acl })
    if ($item.PSIsContainer) {
        foreach ($child in Get-ChildItem -LiteralPath $item.FullName -Force) {
            Scan (CheckedItem $child.FullName)
        }
    }
}
$root = CheckedItem $backend
if (-not $root.PSIsContainer) { throw 'Invalid backend.' }
$var = CheckedDirectory ([IO.Path]::Combine($root.FullName, 'var'))
foreach ($name in @('cache','log')) {
    Scan (CheckedDirectory ([IO.Path]::Combine($var.FullName, $name)))
}
# No mutation until all entries and their original ACLs have been checked.
$changed = [System.Collections.Generic.List[object]]::new()
try {
    foreach ($entry in $entries) {
        $item = CheckedItem $entry.Item.FullName
        $acl = Get-Acl -LiteralPath $item.FullName
        $inherit = [System.Security.AccessControl.InheritanceFlags]::None
        if ($item.PSIsContainer) {
            $inherit = [System.Security.AccessControl.InheritanceFlags]'ContainerInherit, ObjectInherit'
        }
        foreach ($sid in @($cli, $http)) {
            $rule = [System.Security.AccessControl.FileSystemAccessRule]::new(
                $sid, [System.Security.AccessControl.FileSystemRights]::Modify,
                $inherit, [System.Security.AccessControl.PropagationFlags]::None,
                [System.Security.AccessControl.AccessControlType]::Allow)
            $acl.AddAccessRule($rule)
        }
        $changed.Add($entry)
        Set-Acl -LiteralPath $item.FullName -AclObject $acl
    }
} catch {
    $failure = $_
    $rollbackFailed = $false
    for ($i = $changed.Count - 1; $i -ge 0; $i--) {
        try {
            $item = CheckedItem $changed[$i].Item.FullName
            Set-Acl -LiteralPath $item.FullName -AclObject $changed[$i].Original
        } catch { $rollbackFailed = $true }
    }
    if ($rollbackFailed) { throw 'NTFS ACL operation failed; rollback incomplete. Inspect the runtime ACLs before continuing.' }
    throw $failure
}
Write-Output 'runtime-acl-applied'
POWERSHELL;
        $encoded = base64_encode(iconv('UTF-8', 'UTF-16LE', $script));
        $result = self::command(
            [$powershell, '-NoLogo', '-NoProfile', '-NonInteractive', '-EncodedCommand', $encoded],
            ['SYMSVEL_ACL_BACKEND' => $backend, 'SYMSVEL_ACL_HTTP_ACCOUNT' => $httpUser],
        );
        if (trim($result) === 'same-account') {
            return 'CLI et PHP HTTP : même utilisateur, ACL NTFS inchangées.';
        }
        if (!str_contains($result, 'runtime-acl-applied')) {
            throw new \RuntimeException('Résultat ACL NTFS inattendu.');
        }

        return 'ACL NTFS runtime CLI/HTTP : '.$httpUser.' (var/cache et var/log).';
    }

    private static function ensureDirectory(string $path): void
    {
        if (is_link($path) || (!is_dir($path) && !mkdir($path, 0755) && !is_dir($path))) {
            throw new \RuntimeException('Répertoire runtime non fiable : '.$path);
        }
    }

    /** @param list<string> $arguments */
    private static function command(array $arguments, array $environment = []): string
    {
        // Capture to a single temporary stream, avoiding pipe deadlocks.
        $output = tmpfile();
        if ($output === false) {
            throw new \RuntimeException('Impossible de préparer la commande ACL.');
        }
        try {
            $env = $environment === [] ? null : array_merge(getenv() ?: [], $_ENV, $environment);
            $process = proc_open($arguments, [0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'], 1 => $output, 2 => $output], $pipes, null, $env);
            if (!is_resource($process)) {
                throw new \RuntimeException('Impossible de démarrer '.$arguments[0].'.');
            }
            $status = proc_close($process);
            rewind($output);
            $message = (string) stream_get_contents($output);
            if ($status !== 0) {
                throw new \RuntimeException($arguments[0].' : '.trim($message).' (code '.$status.'). Vérifiez ACL, propriétaire et outil système (acl/setfacl sous Linux).');
            }

            return $message;
        } finally {
            fclose($output);
        }
    }
}
