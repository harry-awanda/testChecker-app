param([string]$MysqlExecutable = 'C:\xampp\mysql\bin\mysqld.exe')
$projectRoot = Split-Path -Parent $PSScriptRoot
$configPath = Join-Path $projectRoot '.local\mysql\my.ini'
if (!(Test-Path -LiteralPath $configPath)) { throw 'Konfigurasi instance lokal belum tersedia. Gunakan server MySQL Anda dan atur .env.' }
if (!(Test-Path -LiteralPath $MysqlExecutable)) { throw 'Executable MariaDB tidak ditemukan. Tentukan -MysqlExecutable.' }
& $MysqlExecutable "--defaults-file=$configPath" '--standalone' '--console'
