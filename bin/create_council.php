<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
function ask_council(string $prompt): string { fwrite(STDOUT,$prompt); return trim((string)fgets(STDIN)); }
$email=strtolower(trim($argv[1]??ask_council('Council email: ')));
if(!filter_var($email,FILTER_VALIDATE_EMAIL)){fwrite(STDERR,"A valid email is required.\n");exit(1);}
$name=ask_council('Council member full name: ');
$password=ask_council('Password (12+ characters): ');
if($name===''||strlen($password)<12){fwrite(STDERR,"Name is required; password must be at least 12 characters.\n");exit(1);}
try{
    db()->prepare("INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,'council')")
        ->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT)]);
    fwrite(STDOUT,"Local student council account created.\n");
}catch(Throwable $e){
    fwrite(STDERR,"Could not create council account: {$e->getMessage()}\n");
    exit(1);
}
