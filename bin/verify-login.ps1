param(
    [ValidateSet('Shubham Banerjee', 'Sounak Sikdar')]
    [string] $Identifier = 'Shubham Banerjee',

    [string] $BaseUrl = 'http://localhost:8086',

    [switch] $InspectOnly
)

$ErrorActionPreference = 'Stop'
$phpCommand = Get-Command php -ErrorAction SilentlyContinue
$phpPath = if ($phpCommand) { $phpCommand.Source } else { 'C:\xampp\php\php.exe' }
if (-not (Test-Path -LiteralPath $phpPath -PathType Leaf)) {
    throw 'PHP CLI was not found. Set PHP on PATH or edit this script for the installed PHP path.'
}

$phpScript = Join-Path $PSScriptRoot 'verify-login.php'

if ($InspectOnly) {
    & $phpPath $phpScript '--inspect'
    exit $LASTEXITCODE
}

# Auto-start development server on port 8086 if not listening
if ($BaseUrl -like '*:8086*') {
    $isListening = $false
    try {
        $tcp = Get-NetTCPConnection -LocalPort 8086 -State Listen -ErrorAction SilentlyContinue
        if ($tcp) {
            $isListening = $true
        }
    } catch {
    }

    if (-not $isListening) {
        Write-Host 'Local server on port 8086 is not listening. Starting development server...'
        $workspaceDir = Split-Path -Parent $PSScriptRoot
        $env:APP_BASE_URL = 'http://localhost:8086'
        Start-Process -FilePath $phpPath -ArgumentList '-S', 'localhost:8086', '-t', 'public' -WorkingDirectory $workspaceDir -WindowStyle Hidden
        Start-Sleep -Milliseconds 1200
    }
}

function Convert-SecureStringToPlainText([System.Security.SecureString] $Value) {
    $pointer = [System.Runtime.InteropServices.Marshal]::SecureStringToBSTR($Value)
    try {
        return [System.Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer)
    } finally {
        [System.Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer)
    }
}

$securePassword = Read-Host -Prompt "Password for $Identifier" -AsSecureString
$password = $null
try {
    $password = Convert-SecureStringToPlainText $securePassword
    $env:ADMIN_LOGIN_PASSWORD = $password
    $env:API_BASE_URL = $BaseUrl

    $previousPreference = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try {
        $output = @(& $phpPath $phpScript $Identifier 2>&1)
        $phpExitCode = $LASTEXITCODE
    } finally {
        $ErrorActionPreference = $previousPreference
    }

    foreach ($entry in $output) {
        $text = if ($entry -is [System.Management.Automation.ErrorRecord]) {
            $entry.Exception.Message
        } else {
            [string] $entry
        }
        if ($phpExitCode -ne 0) {
            [Console]::Error.WriteLine($text)
        } else {
            Write-Host $text
        }
    }

    if ($phpExitCode -ne 0) {
        throw 'Administrator login verification failed.'
    }
} finally {
    if ($securePassword) {
        $securePassword.Dispose()
    }
    $password = $null
    Remove-Item Env:\ADMIN_LOGIN_PASSWORD -ErrorAction SilentlyContinue
    Remove-Item Env:\API_BASE_URL -ErrorAction SilentlyContinue
}
