<?php
require __DIR__.'/../vendor/autoload.php';
$root=dirname(__DIR__);
Dotenv\Dotenv::createImmutable($root)->load();
if(!is_file($root.'/.local/mysql/my.ini') || ($_ENV['DB_PORT']??'')!=='3307' || empty($_ENV['LOCAL_MYSQL_ROOT_PASSWORD'])) {
    fwrite(STDERR,"Instance lokal terisolasi tidak terkonfigurasi. Tidak ada server yang dihentikan.\n");exit(1);
}
try {
    $pdo=new PDO('mysql:host=127.0.0.1;port=3307','root',$_ENV['LOCAL_MYSQL_ROOT_PASSWORD'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('SHUTDOWN');
    echo "Instance MariaDB lokal TestChecker dihentikan.\n";
}catch(Throwable $e){fwrite(STDERR,"Tidak dapat menghentikan instance lokal. Periksa apakah server berjalan.\n");exit(1);}
