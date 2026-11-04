<?php
declare(strict_types=1);
function load_env(string $file): void {
    if (!is_file($file)) return;
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line=trim($line); if ($line==='' || str_starts_with($line,'#') || !str_contains($line,'=')) continue;
        [$key,$value]=explode('=',$line,2); $key=trim($key); $value=trim(trim($value), "\"'");
        if (getenv($key)===false) putenv($key.'='.$value);
    }
}
load_env(dirname(__DIR__).'/.env');
$https=(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') || getenv('APP_ENV')==='production';
session_name('iskonnect_session');
session_set_cookie_params(['httponly'=>true,'secure'=>$https,'samesite'=>'Lax','path'=>'/']);
if (session_status()!==PHP_SESSION_ACTIVE) session_start();
function db(): PDO {
    static $pdo=null; if ($pdo instanceof PDO) return $pdo;
    $host=getenv('DB_HOST')?:'127.0.0.1'; $port=getenv('DB_PORT')?:'3306';
    $name=getenv('DB_NAME')?:'iskonnect'; $user=getenv('DB_USER')?:'iskonnect'; $pass=getenv('DB_PASSWORD')?:'';
    $pdo=new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",$user,$pass,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    return $pdo;
}
function h(?string $v): string { return htmlspecialchars($v??'',ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function csrf_token(): string { return $_SESSION['csrf']??=bin2hex(random_bytes(32)); }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="'.h(csrf_token()).'">'; }
function verify_csrf(): void {
    if(!hash_equals($_SESSION['csrf']??'',(string)($_POST['csrf']??''))){http_response_code(419);exit('Your session expired. Refresh the page and try again.');}
}
function current_user(): ?array { return $_SESSION['user']??null; }
function require_role(string $role): array {
    $u=current_user(); if(!$u||$u['role']!==$role){http_response_code(403);exit('You do not have access to this action.');} return $u;
}
function flash(string $kind,string $message): void { $_SESSION['flash']=['kind'=>$kind,'message'=>$message]; }
function redirect(): never { header('Location: /'); exit; }
function login_user(array $u): void {
    session_regenerate_id(true); $_SESSION['user']=['id'=>(int)$u['id'],'name'=>$u['name'],'first_name'=>$u['first_name']??null,'email'=>$u['email'],'role'=>$u['role'],'department'=>$u['department']??null,'course'=>$u['course']??null,'year_level'=>$u['year_level']??null];
    unset($_SESSION['csrf']); csrf_token();
}
