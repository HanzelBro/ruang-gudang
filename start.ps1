param([int]$Port = 8080)
$phpExecutable = 'C:\xampp\php\php.exe'
if (-not (Test-Path -LiteralPath $phpExecutable)) {
    $phpExecutable = (Get-Command php -ErrorAction Stop).Source
}
Write-Host "Ruang Gudang: http://localhost:$Port"
Write-Host 'Pastikan MySQL di XAMPP sudah berjalan. Tekan Ctrl+C untuk berhenti.'
& $phpExecutable -S "127.0.0.1:$Port" -t $PSScriptRoot (Join-Path $PSScriptRoot 'router.php')
