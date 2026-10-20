<?php
require __DIR__.'/runtime.php';
$worker=new NotificationWorker($pdo,$access);
$transport=new TelegramTransport($env('TELEGRAM_BOT_TOKEN'));
$scheduler=new Scheduler($pdo,$outbox,$bookings,$owners[0]);
$once=in_array('--once',$argv,true);$lastRetention=0;
do {
    try {
        $scheduler->run();
        if(time()-$lastRetention>3600){(new PrivacyService($pdo))->retain((int)$env('RETENTION_DAYS','180'));$lastRetention=time();}
        $worker->tick($transport,20);
    }catch(Throwable $error){error_log('Background worker failure: '.get_class($error).'/'.$error->getCode());if($once)exit(1);}
    if(!$once)sleep(1);
}while(!$once);
