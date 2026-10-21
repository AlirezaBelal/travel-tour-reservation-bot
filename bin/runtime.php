<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../src/product_bootstrap.php';
require_once __DIR__.'/../src/validators.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
$env=static function(string$key,string$default=''):string{$value=$_ENV[$key]??getenv($key);return$value===false?$default:(string)$value;};
$host=$env('DB_SERVER');$database=$env('DB_DATABASE');$port=$env('DB_PORT','3306');
if(!preg_match('/^[A-Za-z0-9_.-]+$/D',$host)||!preg_match('/^[A-Za-z0-9_]+$/D',$database)||!ctype_digit($port))throw new RuntimeException('Invalid database configuration.');
$pdo=new PDO("mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4",$env('DB_USERNAME'),$env('DB_PASSWORD'));
Database::configure($pdo);
$owners=parseAdminIds($env('TELEGRAM_ADMIN_IDS'));
if($owners===[])throw new RuntimeException('At least one owner required.');
$access=new AccessControl($pdo,$owners);$outbox=new Outbox($pdo);$paymentReview=new PaymentReviewService($pdo,$access,$outbox);$bookings=BookingServiceFactory::create($pdo,$access,$outbox,$paymentReview);
