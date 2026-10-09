param(
    [Parameter(Mandatory = $true)]
    [ValidateSet('provision', 'rotate')]
    [string] $Mode,

    [ValidateSet('Shubham Banerjee', 'Sounak Sikdar')]
    [string] $Identifier
)

$ErrorActionPreference = 'Stop'
$phpCommand = Get-Command php -ErrorAction SilentlyContinue
$phpPath = if ($phpCommand) { $phpCommand.Source } else { 'C:\xampp\php\php.exe' }
if (-not (Test-Path -LiteralPath $phpPath -PathType Leaf)) {
    throw 'PHP CLI was not found. Set PHP on PATH or edit this script for the installed PHP path.'
}

function Convert-SecureStringToPlainText([System.Security.SecureString] $Value) {
    $pointer = [System.Runtime.InteropServices.Marshal]::SecureStringToBSTR($Value)
    try {
        return [System.Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer)
    } finally {
        [System.Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer)
    }
}

function Read-ConfirmedPassword([string] $Prompt) {
    while ($true) {
        $firstSecure = Read-Host -Prompt $Prompt -AsSecureString
        $secondSecure = Read-Host -Prompt 'Confirm password' -AsSecureString
        $first = Convert-SecureStringToPlainText $firstSecure
        $second = Convert-SecureStringToPlainText $secondSecure
        $firstSecure.Dispose()
        $secondSecure.Dispose()
        if ($first -ceq $second) {
            $second = $null
            return $first
        }
        $first = $null
        $second = $null
        Write-Host 'Passwords did not match. Try again.'
    }
}

$passwords = @()
try {
    if ($Mode -eq 'provision') {
        $passwords += Read-ConfirmedPassword 'Password for Shubham Banerjee'
        $passwords += Read-ConfirmedPassword 'Password for Sounak Sikdar'
        if ($passwords[0] -ceq $passwords[1]) {
            throw 'Use distinct passwords for the two administrator accounts.'
        }
        $env:ADMIN_SHUBHAM_PASSWORD = $passwords[0]
        $env:ADMIN_SOUNAK_PASSWORD = $passwords[1]
        & $phpPath (Join-Path $PSScriptRoot 'admin-credentials.php') provision
    } else {
        if (-not $Identifier) {
            throw 'For rotation, provide -Identifier "Shubham Banerjee" or "Sounak Sikdar".'
        }
        $passwords += Read-ConfirmedPassword "New password for $Identifier"
        $env:ADMIN_NEW_PASSWORD = $passwords[0]
        & $phpPath (Join-Path $PSScriptRoot 'admin-credentials.php') rotate $Identifier
    }
    if ($LASTEXITCODE -ne 0) {
        throw 'Administrator credential operation failed.'
    }
} finally {
    Remove-Item Env:ADMIN_SHUBHAM_PASSWORD -ErrorAction SilentlyContinue
    Remove-Item Env:ADMIN_SOUNAK_PASSWORD -ErrorAction SilentlyContinue
    Remove-Item Env:ADMIN_NEW_PASSWORD -ErrorAction SilentlyContinue
    for ($index = 0; $index -lt $passwords.Count; $index++) {
        $passwords[$index] = $null
    }
    $passwords = $null
}
