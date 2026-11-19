<?php
// Roteador exclusivo do servidor de teste: simula a instalação em subpasta no Apache.
if (PHP_SAPI !== 'cli-server' || !getenv('LIVRARIA_CONFIG_FILE')) { http_response_code(404); exit; }
$root=dirname(__DIR__).'/public'; $base='/livraria-eric-aslan';
$uri=(string)parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if (!str_starts_with($uri,$base.'/') && $uri!==$base) { http_response_code(404); exit; }
$relative=substr($uri,strlen($base)); $file=realpath($root.$relative);
if ($file && str_starts_with(str_replace('\\','/',$file),str_replace('\\','/',realpath($root)).'/') && is_file($file) && !str_ends_with($file,'.php')) {
    $type=['css'=>'text/css','js'=>'application/javascript','svg'=>'image/svg+xml','png'=>'image/png','jpg'=>'image/jpeg','webp'=>'image/webp'][pathinfo($file,PATHINFO_EXTENSION)]??'application/octet-stream';
    header('Content-Type: '.$type); readfile($file); exit;
}
$_SERVER['SCRIPT_NAME']=$base.'/public/index.php';
require $root.'/index.php';
