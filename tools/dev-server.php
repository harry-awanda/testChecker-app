<?php
// Router for the PHP development server. Public files remain under public/.
$public=realpath(__DIR__.'/../public');
$path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH);
$file=realpath($public.'/'.rawurldecode($path?:'/'));
if($file && str_starts_with($file,$public.DIRECTORY_SEPARATOR) && is_file($file)) return false;
require $public.'/index.php';
