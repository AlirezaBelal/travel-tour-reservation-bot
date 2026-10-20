<?php
try { require __DIR__.'/runtime.php'; }
catch(Throwable $error){error_log('Worker initialization failed: '.get_class($error).'/'.$error->getCode());exit(1);}
if(in_array('--health',$argv,true)){Database::run($pdo,"SELECT COUNT(*) FROM NotificationOutbox")->fetchColumn();echo "Worker dependencies ready.\n";exit;}
$worker=new NotificationWorker($pdo,$access);
$transport=new TelegramTransport($env('TELEGRAM_BOT_TOKEN'));
$scheduler=new Scheduler($pdo,$outbox,$bookings,$owners[0]);
$once=in_array('--once',$argv,true);$lastRetention=0;
do {
    try {
        $scheduler->run();
        if(time()-$lastRetention>3600){$privacy=new PrivacyService($pdo);if($env('RETENTION_ENABLED','false')==='true')$privacy->retain((int)$env('RETENTION_DAYS','180'));$privacy->cleanup();$lastRetention=time();}
        $worker->tick($transport,20);
    }catch(Throwable $error){
        error_log('Background worker failure: '.get_class($error).'/'.$error->getCode());
        // A disconnected PDO cannot heal itself. Let the process supervisor create
        // a fresh runtime/connection; processing leases remain durable in MySQL.
        if($once || $error instanceof PDOException)exit(1);
    }
    if(!$once)sleep(1);
}while(!$once);
