<?php
// Real subprocess crash/restart; only the explicitly allowlisted synthetic DB.
require __DIR__.'/../src/product_bootstrap.php';
$dsn=getenv('TEST_MYSQL_DSN');
if(!$dsn||preg_match('/(?:^|;)dbname=travel_tour_test(?:;|$)/D',$dsn)!==1)throw new RuntimeException('Isolated test database required.');
$pdo=new PDO($dsn,getenv('TEST_MYSQL_USER')?:'root',getenv('TEST_MYSQL_PASSWORD')?:'');Database::configure($pdo);
$access=new AccessControl($pdo,[999]);$worker=new NotificationWorker($pdo,$access);
if(($argv[1]??'')==='child') {
    $mode=$argv[2]??'';
    $worker->tick(function()use($mode){
        if($mode==='crash')while(true)usleep(10000); // Parent kills us during external send.
        return $mode==='temporary'?['ok'=>false,'code'=>503]:['ok'=>true,'code'=>200];
    },1,false);
    exit;
}
$checks=0;
function processCheck(bool $ok,string $label):void{global$checks;$checks++;if(!$ok)throw new RuntimeException($label);}
function launchWorker(string $mode):array{
    $pipes=[];$process=proc_open([PHP_BINARY,__FILE__,'child',$mode],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($process))throw new RuntimeException('Worker subprocess unavailable.');
    fclose($pipes[0]);return[$process,$pipes];
}
function finishWorker(array $child):void{
    [$process,$pipes]=$child;
    // Bound every wait, including error paths, so a regression cannot hang CI.
    $deadline=microtime(true)+10;
    do{$status=proc_get_status($process);if(!$status['running'])break;usleep(10000);}while(microtime(true)<$deadline);
    if($status['running'])proc_terminate($process,9);
    $output=stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);proc_close($process);
    if($status['running']||$status['exitcode']!==0||$output!=='')throw new RuntimeException('Worker subprocess failed.');
}
$pdo->exec('DELETE FROM NotificationOutbox');
$outbox=new Outbox($pdo);$outbox->message(880,'Synthetic crash recovery','process-crash');
$child=launchWorker('crash');
try {
    $deadline=microtime(true)+10;
    do{$row=$pdo->query('SELECT status,attempts FROM NotificationOutbox')->fetch(PDO::FETCH_ASSOC);if($row['status']==='processing')break;usleep(10000);}while(microtime(true)<$deadline);
    processCheck($row['status']==='processing'&&(int)$row['attempts']===1,'child owns durable processing lease');
    // Another OS process must respect the live worker advisory lock.
    finishWorker(launchWorker('success'));
    processCheck($pdo->query('SELECT status FROM NotificationOutbox')->fetchColumn()==='processing','concurrent worker cannot steal live send');
} finally {
    proc_terminate($child[0],9);foreach($child[1]as$pipe)if(is_resource($pipe))fclose($pipe);proc_close($child[0]);
}
finishWorker(launchWorker('success'));
processCheck($pdo->query('SELECT status FROM NotificationOutbox')->fetchColumn()==='processing','fresh crash lease is not immediately duplicated');
// Advance only the test lease rather than waiting five minutes in CI.
$pdo->exec('UPDATE NotificationOutbox SET locked_at=UTC_TIMESTAMP()-INTERVAL 6 MINUTE');
finishWorker(launchWorker('success'));
$row=$pdo->query('SELECT status,attempts,locked_at FROM NotificationOutbox')->fetch(PDO::FETCH_ASSOC);
processCheck($row['status']==='sent'&&(int)$row['attempts']===2&&$row['locked_at']===null,'new process recovers expired crash lease');
$outbox->message(881,'Synthetic service outage','process-outage');finishWorker(launchWorker('temporary'));
$row=$pdo->query("SELECT * FROM NotificationOutbox WHERE status='pending'")->fetch(PDO::FETCH_ASSOC);
processCheck($row&&$row['error_code']==='telegram_503'&&(int)$row['attempts']===1&&$row['available_at']>$row['created_at'],'temporary outage persists retry across process exit');
// Give slow CI machines a deterministic not-yet-due window.
$pdo->exec("UPDATE NotificationOutbox SET available_at=UTC_TIMESTAMP()+INTERVAL 1 MINUTE WHERE status='pending'");
finishWorker(launchWorker('success'));
processCheck((int)$pdo->query("SELECT COUNT(*) FROM NotificationOutbox WHERE status='pending'")->fetchColumn()===1,'restart respects persisted backoff');
$pdo->exec("UPDATE NotificationOutbox SET available_at=UTC_TIMESTAMP() WHERE status='pending'");finishWorker(launchWorker('success'));
processCheck((int)$pdo->query("SELECT COUNT(*) FROM NotificationOutbox WHERE status='sent'")->fetchColumn()===2,'retry delivered after service recovery');
echo "Worker process recovery tests passed ($checks checks).\n";
