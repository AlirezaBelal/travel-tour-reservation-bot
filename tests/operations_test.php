<?php
set_error_handler(static function(int $severity,string $message,string $file,int $line):bool {
    if ($severity & (E_WARNING | E_NOTICE | E_USER_WARNING)) throw new ErrorException($message,0,$severity,$file,$line);
    return false;
});
require __DIR__.'/../src/product_bootstrap.php';
$dsn=getenv('TEST_MYSQL_DSN');if(!$dsn||preg_match('/(?:^|;)dbname=travel_tour_test(?:;|$)/D',$dsn)!==1)throw new RuntimeException('Isolated test database required.');
$pdo=new PDO($dsn,getenv('TEST_MYSQL_USER')?:'root',getenv('TEST_MYSQL_PASSWORD')?:'');Database::configure($pdo);
$access=new AccessControl($pdo,[999]);$outbox=new Outbox($pdo);$service=new BookingService($pdo,$access,$outbox);$worker=new NotificationWorker($pdo,$access);
$checks=0;
function opCheck(bool $condition,string $label):void{global$checks;$checks++;if(!$condition)throw new RuntimeException($label);}
function opDenied(callable $action):void{try{$action();throw new RuntimeException('Expected rejection.');}catch(RuleViolation){opCheck(true,'rule rejection');}}
for($i=400;$i<430;$i++){$service->ensureUser($i);Database::run($pdo,'UPDATE Data SET Name=? WHERE UserId=?',['Synthetic person',$i]);}
$values=['category_id'=>null,'title'=>'Synthetic operations','destination'=>'Synthetic destination','description'=>'Synthetic description','starts_at'=>gmdate('Y-m-d H:i:s',time()+3600),'ends_at'=>gmdate('Y-m-d H:i:s',time()+7200),'price'=>100,'capacity'=>20,'publication'=>'published'];
// Long Persian replies are split without breaking UTF-8 or duplicating keyboards.
$outbox->message(400,str_repeat('ش',4000),'long-unicode',[[['text'=>'Synthetic','callback_data'=>'p:home:0']]]);
opCheck((int)Database::run($pdo,'SELECT COUNT(*) FROM NotificationOutbox WHERE idem_key IN (?,?)',[hash('sha256','long-unicode:part:0'),hash('sha256','long-unicode:part:1')])->fetchColumn()===2,'unicode message splitting');
// Sandbox verifies signatures, order, amount, expiry, unique transactions and replay.
$tour=$service->saveTour(999,$values);$id=$service->reserve(400,$tour,1,'sandbox-one');$booking=$service->booking(400,$id);
$adapter=new SandboxPaymentAdapter(str_repeat('s',48));$payments=new PaymentService($pdo,$outbox);
$event=['order_ref'=>$booking['order_ref'],'transaction'=>'SYNTHETIC-TX-ONE','amount'=>100,'status'=>'paid','timestamp'=>time()];$event['signature']=$adapter->signature($event);
opDenied(fn()=>$payments->callback($adapter,array_replace($event,['signature'=>'bad'])));
$wrong=array_replace($event,['amount'=>101]);$wrong['signature']=$adapter->signature($wrong);opDenied(fn()=>$payments->callback($adapter,$wrong));
opCheck($payments->callback($adapter,$event),'signed sandbox payment');opCheck(!$payments->callback($adapter,$event),'duplicate sandbox callback');
$second=$service->reserve(401,$tour,1,'sandbox-two');$event2=array_replace($event,['order_ref'=>$service->booking(401,$second)['order_ref']]);$event2['signature']=$adapter->signature($event2);opDenied(fn()=>$payments->callback($adapter,$event2));
opCheck($service->booking(401,$second)['payment_status']==='pending','duplicate transaction rollback');
$failure=array_replace($event2,['transaction'=>'SYNTHETIC-TX-FAIL','status'=>'failed']);$failure['signature']=$adapter->signature($failure);opCheck($payments->callback($adapter,$failure),'failed sandbox callback');opCheck(!$payments->callback($adapter,$failure),'duplicate failed callback');
$expired=$service->reserve(402,$tour,1,'sandbox-expired');Database::run($pdo,'UPDATE Payments SET expires_at=? WHERE booking_id=?',[gmdate('Y-m-d H:i:s',time()-60),$expired]);
$event3=array_replace($event,['order_ref'=>$service->booking(402,$expired)['order_ref'],'transaction'=>'SYNTHETIC-TX-EXPIRED']);$event3['signature']=$adapter->signature($event3);opDenied(fn()=>$payments->callback($adapter,$event3));
$stale=array_replace($event,['timestamp'=>time()-600]);$stale['signature']=$adapter->signature($stale);opDenied(fn()=>$payments->callback($adapter,$stale));
$unknown=array_replace($event,['order_ref'=>str_repeat('a',32)]);$unknown['signature']=$adapter->signature($unknown);opDenied(fn()=>$payments->callback($adapter,$unknown));
$failedHold=$service->reserve(407,$tour,1,'failed-hold-expiry');Database::run($pdo,"UPDATE Payments SET status='failed',expires_at=? WHERE booking_id=?",[gmdate('Y-m-d H:i:s',time()-60),$failedHold]);
// Every worker scenario starts with only synthetic queue data.
$pdo->exec('DELETE FROM NotificationOutbox');
$outbox->message(400,'Synthetic notification','same-key');$outbox->message(400,'Synthetic notification','same-key');opCheck((int)$pdo->query('SELECT COUNT(*) FROM NotificationOutbox')->fetchColumn()===1,'outbox enqueue idempotency');
$worker->tick(fn()=>['ok'=>false,'code'=>429,'retry_after'=>45],1,false);
$row=$pdo->query('SELECT * FROM NotificationOutbox')->fetch(PDO::FETCH_ASSOC);opCheck($row['status']==='pending'&&(int)$row['attempts']===1&&strtotime($row['available_at'].' UTC')>=time()+43,'429 retry-after honored');
$pdo->exec('UPDATE NotificationOutbox SET available_at=UTC_TIMESTAMP()');$worker->tick(fn()=>['ok'=>false,'code'=>503],1,false);
$row=$pdo->query('SELECT * FROM NotificationOutbox')->fetch(PDO::FETCH_ASSOC);opCheck((int)$row['attempts']===2&&$row['error_code']==='telegram_503','temporary API failure retried');
$pdo->exec('UPDATE NotificationOutbox SET available_at=UTC_TIMESTAMP()');$worker->tick(fn()=>['ok'=>true,'code'=>200],1,false);
opCheck($pdo->query('SELECT status FROM NotificationOutbox')->fetchColumn()==='sent','successful retry persisted');
$outbox->message(401,'Synthetic blocked chat','blocked');$worker->tick(fn()=>['ok'=>false,'code'=>403],1,false);opCheck((int)$pdo->query("SELECT COUNT(*) FROM NotificationOutbox WHERE status='failed' AND error_code='telegram_403'")->fetchColumn()===1,'permanent failures not retried automatically');
$failed=(int)$pdo->query("SELECT id FROM NotificationOutbox WHERE status='failed' LIMIT 1")->fetchColumn();opDenied(fn()=>$outbox->retry(400,$failed,$access));$outbox->retry(999,$failed,$access);$worker->tick(fn()=>['ok'=>true,'code'=>200],1,false);
opCheck(Database::run($pdo,'SELECT status FROM NotificationOutbox WHERE id=?',[$failed])->fetchColumn()==='sent','authorized manual retry');
$outbox->message(402,'Synthetic restart','restart');$pdo->exec("UPDATE NotificationOutbox SET status='processing',locked_at=UTC_TIMESTAMP()-INTERVAL 6 MINUTE WHERE status='pending'");$worker->tick(fn()=>['ok'=>true,'code'=>200],1,false);opCheck((int)$pdo->query("SELECT COUNT(*) FROM NotificationOutbox WHERE status='processing'")->fetchColumn()===0,'crashed worker lease recovered');
opCheck(NotificationWorker::delay(2)===4&&NotificationWorker::delay(20)===3600,'bounded exponential backoff');
$access->assign(999,403,'manager');$outbox->enqueue(403,'sendDocument',['_csv'=>'Synthetic CSV','_export_actor'=>403],'revoked-export');$access->assign(999,403,'none');$sent=0;$worker->tick(function()use(&$sent){$sent++;return['ok'=>true,'code'=>200];},1,false);opCheck($sent===0,'export permission checked again at delivery');
$access->assign(999,403,'manager');$outbox->message(403,'Synthetic personal profile','profile-permission',[],400,['profiles']);$access->assign(999,403,'none');$sent=0;$worker->tick(function()use(&$sent){$sent++;return['ok'=>true,'code'=>200];},1,false);opCheck($sent===0,'profile permissions checked again before delivery');
// Reminder creation is durable/idempotent; obsolete reminders are suppressed.
$service->transition(999,$id,'confirmed',null,true);$pdo->exec('DELETE FROM NotificationOutbox');$scheduler=new Scheduler($pdo,$outbox,$service,999);$scheduler->run();$scheduler->run();
opCheck((int)$pdo->query("SELECT COUNT(*) FROM NotificationOutbox WHERE payload LIKE '%_reminder_booking%'")->fetchColumn()===1,'one reminder per booking and tour version');
opCheck($service->booking(402,$expired)['status']==='rejected'&&$service->booking(402,$expired)['payment_status']==='expired','expired unpaid hold rejected and released');
$service->transition(400,$id,'cancelled');$pdo->exec("DELETE FROM NotificationOutbox WHERE payload NOT LIKE '%_reminder_booking%'");$sent=0;$worker->tick(function()use(&$sent){$sent++;return['ok'=>true,'code'=>200];},20,false);opCheck($sent===0,'cancelled booking reminder suppressed');
opCheck($service->booking(407,$failedHold)['status']==='rejected'&&$service->booking(407,$failedHold)['payment_status']==='expired','failed unpaid payment hold also expires');
// Completion is permitted only after a confirmed tour ends.
$free=$service->saveTour(999,array_replace($values,['price'=>0]));$done=$service->reserve(404,$free,1,'complete');$service->transition(999,$done,'confirmed',null,true);opDenied(fn()=>$service->transition(999,$done,'completed',null,true));
Database::run($pdo,'UPDATE Tours SET starts_at=?,ends_at=? WHERE id=?',[gmdate('Y-m-d H:i:s',time()-7200),gmdate('Y-m-d H:i:s',time()-3600),$free]);opDenied(fn()=>$service->transition(404,$done,'cancelled'));opCheck($service->transition(999,$done,'completed',null,true),'completed lifecycle');
// Deletion removes legacy PII, participants and account linkage; active holds block it.
$privacy=new PrivacyService($pdo);opDenied(fn()=>$privacy->erase(401));$service->transition(401,$second,'cancelled');Database::run($pdo,"UPDATE Data SET Mobile='09121234567',N_Code='1234567891' WHERE UserId=401");$privacy->erase(401);
opCheck(Database::run($pdo,'SELECT UserId FROM Data WHERE UserId=401')->fetchColumn()===false,'profile including legacy PII deleted');opCheck(Database::run($pdo,'SELECT user_id FROM Bookings WHERE id=?',[$second])->fetchColumn()===null,'booking anonymized');opCheck((int)Database::run($pdo,'SELECT COUNT(*) FROM BookingParticipants WHERE booking_id=?',[$second])->fetchColumn()===0,'participants erased');
Database::run($pdo,'UPDATE Data SET updated_at=? WHERE UserId IN (405,406)',[gmdate('Y-m-d H:i:s',time()-200*86400)]);$hold=$service->reserve(406,$tour,1,'retention-active');Database::run($pdo,'UPDATE Data SET updated_at=? WHERE UserId=406',[gmdate('Y-m-d H:i:s',time()-200*86400)]);$privacy->retain(180);opCheck(!Database::run($pdo,'SELECT UserId FROM Data WHERE UserId=405')->fetchColumn(),'inactive user retention');opCheck((bool)Database::run($pdo,'SELECT UserId FROM Data WHERE UserId=406')->fetchColumn(),'active reservation survives retention');
fwrite(STDOUT,"Operations tests passed ($checks checks).\n");
