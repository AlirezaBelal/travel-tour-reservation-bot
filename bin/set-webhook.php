<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../src/webhook.php';
require __DIR__.'/../src/WebhookRegistration.php';
require __DIR__.'/../src/product_bootstrap.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
$env=static function(string$key):string{$value=$_ENV[$key]??getenv($key);return$value===false?'':(string)$value;};
try{
    if(count($argv)!==3||!in_array($argv[2],['--check','--confirm'],true))throw new InvalidArgumentException('Usage: php bin/set-webhook.php https://YOUR_DOMAIN/index.php --check|--confirm');
    $payload=WebhookRegistration::parameters($argv[1],$env('TELEGRAM_WEBHOOK_SECRET'));
    if($env('TELEGRAM_BOT_TOKEN')==='')throw new InvalidArgumentException('Configure TELEGRAM_BOT_TOKEN privately.');
    if($argv[2]==='--check'){echo"Webhook configuration syntax valid; token, domain and TLS were not contacted.\n";exit;}
    $result=(new TelegramTransport($env('TELEGRAM_BOT_TOKEN')))('setWebhook',$payload);
    if(!$result['ok']){fwrite(STDERR,'Webhook registration not confirmed; API/transport code '.(int)$result['code'].". Check configuration privately.\n");exit(1);}
    echo"Webhook registration confirmed by Telegram. Existing pending updates were preserved.\n";
}catch(InvalidArgumentException$error){fwrite(STDERR,$error->getMessage()."\n");exit(1);}
catch(Throwable){fwrite(STDERR,"Webhook configuration failed; inspect deployment privately.\n");exit(1);}
