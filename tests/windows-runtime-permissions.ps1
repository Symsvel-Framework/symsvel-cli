$ErrorActionPreference = 'Stop'
$root = Join-Path $env:RUNNER_TEMP ('symsvel acl ' + [guid]::NewGuid().ToString('N'))
$name = 'sym' + [guid]::NewGuid().ToString('N').Substring(0,10)
$password = ConvertTo-SecureString ('Zk!8a'+[guid]::NewGuid().ToString('N')) -AsPlainText -Force
$user = New-LocalUser -Name $name -Password $password -Description 'Disposable SymSvel CI HTTP user'
$account = "$env:COMPUTERNAME\$name"
try {
    New-Item -ItemType Directory -Path "$root/var/cache/nested","$root/var/log" -Force | Out-Null
    Set-Content -LiteralPath "$root/var/cache/nested/old.php" -Value 'original'
    Set-Content -LiteralPath "$root/var/app.sqlite" -Value 'private'
    $private = (Get-Acl -LiteralPath "$root/var/app.sqlite").Sddl
    $before = (Get-Acl -LiteralPath "$root/var/cache").Sddl
    New-Item -ItemType HardLink -Path "$root/var/cache/outside" -Target "$root/var/app.sqlite" | Out-Null
    php tests/windows-runtime-permissions.php $root $account refuse-link
    if ($LASTEXITCODE -ne 0) { throw 'Hardlink rejection failed.' }
    if ((Get-Acl -LiteralPath "$root/var/cache").Sddl -ne $before) { throw 'ACL mutated before preflight completed.' }
    Remove-Item -LiteralPath "$root/var/cache/outside"
    php tests/windows-runtime-permissions.php $root $account
    if ($LASTEXITCODE -ne 0) { throw 'ACL application failed.' }
    # Existing directories and files, and a new descendant inheriting the rule.
    New-Item -ItemType Directory -Path "$root/var/cache/new" | Out-Null
    Set-Content -LiteralPath "$root/var/cache/new/generated.php" -Value 'generated'
    foreach ($path in @("$root/var/cache","$root/var/cache/nested/old.php","$root/var/cache/new/generated.php","$root/var/log")) {
        $rules = (Get-Acl -LiteralPath $path).GetAccessRules($true,$true,[System.Security.Principal.SecurityIdentifier])
        $modify = [System.Security.AccessControl.FileSystemRights]::Modify
        $matching = @($rules | Where-Object { $_.IdentityReference.Value -eq $user.SID.Value -and $_.AccessControlType -eq 'Allow' -and ($_.FileSystemRights -band $modify) -eq $modify })
        if ($matching.Count -eq 0) { throw "Missing scoped Modify ACL: $path" }
    }
    if ((Get-Acl -LiteralPath "$root/var/app.sqlite").Sddl -ne $private) { throw 'Private database ACL changed.' }
    php tests/windows-runtime-permissions.php $root $account
    if ($LASTEXITCODE -ne 0) { throw 'Repeat ACL application failed.' }
    Write-Output 'Windows scoped NTFS ACL, inherited rights, hardlink preflight and private database preservation: OK'
} finally {
    Remove-Item -LiteralPath $root -Recurse -Force -ErrorAction SilentlyContinue
    Remove-LocalUser -Name $name
}
