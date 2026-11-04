<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
function ask(string $prompt): string { fwrite(STDOUT,$prompt); return trim((string)fgets(STDIN)); }
$email=strtolower(trim($argv[1]??ask('Officer email: ')));
if(!filter_var($email,FILTER_VALIDATE_EMAIL)){fwrite(STDERR,"A valid email is required.\n");exit(1);}
$name=ask('Officer full name: ');
$password=ask('Strong password (12+ characters): ');
if($name===''||strlen($password)<12){fwrite(STDERR,"Name is required; password must be at least 12 characters.\n");exit(1);}
$ids=array_values(array_filter(array_map('trim',explode(',',ask('Organization IDs, comma-separated (e.g. ssc,pub): ')))));
$pdo=db(); $pdo->beginTransaction();
try{
 $q=$pdo->prepare("INSERT INTO users(name,email,password_hash,role) VALUES(?,?,?,'officer')");
 $q->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT)]); $uid=(int)$pdo->lastInsertId();
 $add=$pdo->prepare('INSERT INTO officer_organizations(officer_id,organization_id) SELECT ?,id FROM organizations WHERE id=?');
 foreach($ids as $id){$add->execute([$uid,$id]);if($add->rowCount()!==1)throw new RuntimeException("Unknown organization: {$id}");}
 $pdo->commit();fwrite(STDOUT,"Officer created. Provide the credentials privately.\n");
}catch(Throwable $e){$pdo->rollBack();fwrite(STDERR,"Could not create officer: {$e->getMessage()}\n");exit(1);}
