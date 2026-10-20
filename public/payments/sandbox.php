<?php
// Local simulator callback only. A real provider must implement server verification.
require dirname(__DIR__,2).'/vendor/autoload.php';
require dirname(__DIR__,2).'/src/product_bootstrap.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__,2))->safeLoad();
$env=static function(string$key,string$default=''):string{$value=$_ENV[$key]??getenv($key);return$value===false?$default:(string)$value;};
header('Content-Type: application/json');header('Cache-Control: no-store');
if($env('PAYMENT_SANDBOX_ENABLED')!=='true'){http_response_code(404);echo'{"status":"disabled"}';exit;}
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);header('Allow: POST');exit;}
try {
    $raw=file_get_contents('php://input',false,null,0,8193);
    if(!is_string($raw)||strlen($raw)>8192){http_response_code(413);exit;}
    $payload=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
    if(!is_array($payload))throw new RuleViolation('Invalid callback.');
    $adapter=new SandboxPaymentAdapter($env('PAYMENT_SANDBOX_SECRET'));$adapter->verify($payload);
    $host=$env('DB_SERVER');$database=$env('DB_DATABASE');$port=$env('DB_PORT','3306');
    if(!preg_match('/^[A-Za-z0-9_.-]+$/D',$host)||!preg_match('/^[A-Za-z0-9_]+$/D',$database)||!ctype_digit($port))throw new RuntimeException('Invalid configuration.');
    $pdo=new PDO("mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4",$env('DB_USERNAME'),$env('DB_PASSWORD'));Database::configure($pdo);
    $changed=(new PaymentService($pdo,new Outbox($pdo)))->callback($adapter,$payload);
    echo json_encode(['status'=>$changed?'processed':'duplicate']);
}catch(RuleViolation|JsonException){http_response_code(400);echo'{"status":"rejected"}';}
catch(Throwable){http_response_code(503);echo'{"status":"unavailable"}';}
