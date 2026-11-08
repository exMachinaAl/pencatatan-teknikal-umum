param(
    [string] $HostAddress = '127.0.0.1',
    [ValidateRange(1, 65535)]
    [int] $Port = 8000,
    [switch] $Optimize
)

$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $PSScriptRoot
$phpBinary = (Get-Command php -ErrorAction Stop).Source
$phpDirectory = Split-Path -Parent $phpBinary
$extensionDirectory = Join-Path $phpDirectory 'ext'
$opcachePath = Join-Path $extensionDirectory 'php_opcache.dll'
$routerPath = Join-Path $projectRoot 'vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php'
$publicPath = Join-Path $projectRoot 'public'

if (-not (Test-Path -LiteralPath $opcachePath -PathType Leaf)) {
    throw "OPcache tidak ditemukan: $opcachePath"
}

if (-not (Test-Path -LiteralPath $routerPath -PathType Leaf)) {
    throw 'Router Laravel belum tersedia. Jalankan composer install terlebih dahulu.'
}

if ($Optimize) {
    Push-Location $projectRoot
    try {
        & $phpBinary artisan optimize
        if ($LASTEXITCODE -ne 0) {
            throw 'Optimasi Laravel gagal.'
        }
    } finally {
        Pop-Location
    }
}

Write-Host "Server cepat TU: http://${HostAddress}:$Port"
Write-Host 'OPcache aktif hanya untuk proses server ini. Tekan Ctrl+C untuk berhenti.'

Push-Location $publicPath
try {
    & $phpBinary `
        '-d' "zend_extension=$opcachePath" `
        '-d' 'opcache.enable=1' `
        '-d' 'opcache.enable_cli=1' `
        '-d' 'opcache.memory_consumption=192' `
        '-d' 'opcache.interned_strings_buffer=16' `
        '-d' 'opcache.max_accelerated_files=20000' `
        '-d' 'opcache.validate_timestamps=1' `
        '-d' 'opcache.revalidate_freq=0' `
        '-S' "${HostAddress}:$Port" `
        $routerPath
} finally {
    Pop-Location
}
