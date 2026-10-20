<?php
require __DIR__ . '/../src/product_bootstrap.php';
require __DIR__ . '/../database/product.php';
$dsn = getenv('TEST_MYSQL_DSN');
if (!$dsn || preg_match('/(?:^|;)dbname=travel_tour_test(?:;|$)/D',$dsn)!==1) { throw new RuntimeException('Isolated test database required.'); }
$pdo = new PDO($dsn,getenv('TEST_MYSQL_USER')?:'root',getenv('TEST_MYSQL_PASSWORD')?:'', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
Database::configure($pdo);
$access = new AccessControl($pdo,[999]);
$outbox = new Outbox($pdo);
$service = new BookingService($pdo,$access,$outbox);
if (($argv[1]??'')==='worker') {
    try {
        // Deliberately establish an old snapshot before competing for the tour lock.
        Database::atomic($pdo,function()use($pdo,$service,$argv){
            $pdo->query('SELECT COUNT(*) FROM Bookings')->fetchColumn();
            usleep(50000);
            $service->reserve((int)$argv[2],(int)$argv[3],1,'concurrency');
        });
        echo 'created';
    } catch (RuleViolation) { if($pdo->inTransaction())$pdo->rollBack(); echo 'full'; }
    exit;
}
$checks=0;
function productCheck(bool $condition,string $label):void { global $checks; $checks++; if(!$condition) throw new RuntimeException($label); }
function denied(callable $call):void { try{$call();throw new RuntimeException('Expected rule rejection.');}catch(RuleViolation){productCheck(true,'rule rejection');} }
migrateProduct($pdo); migrateProduct($pdo);
productCheck((int)$pdo->query("SELECT COUNT(*) FROM SchemaMigrations WHERE version='002_product'")->fetchColumn()===1,'repeatable additive migration');
productCheck((int)$pdo->query('SELECT COUNT(*) FROM Tours')->fetchColumn()===0,'no invented legacy tours');
for($i=300;$i<330;$i++) { $service->ensureUser($i); Database::run($pdo,'UPDATE Data SET Name=? WHERE UserId=?',['Synthetic traveler',$i]); }
$values=['category_id'=>null,'title'=>'Synthetic Tour','destination'=>'Synthetic destination','description'=>'Synthetic description','starts_at'=>gmdate('Y-m-d H:i:s',time()+172800),'ends_at'=>gmdate('Y-m-d H:i:s',time()+180000),'price'=>0,'capacity'=>3,'publication'=>'published'];
denied(fn()=>$service->saveTour(300,$values));
$tour=$service->saveTour(999,$values);
$booking=$service->reserve(300,$tour,2,'request-one');
productCheck($service->reserve(300,$tour,2,'request-one')===$booking,'idempotent booking');
productCheck($service->used($tour)===2,'group holds capacity');
productCheck((int)Database::run($pdo,'SELECT COUNT(*) FROM BookingParticipants WHERE booking_id=?',[$booking])->fetchColumn()===2,'group participant rows');
denied(fn()=>$service->reserve(301,$tour,2,'request-two'));
denied(fn()=>$service->booking(301,$booking));
denied(fn()=>$service->transition(301,$booking,'confirmed',null,true));
productCheck($service->transition(999,$booking,'confirmed',null,true),'free booking confirmed');
productCheck(!$service->transition(999,$booking,'confirmed',null,true),'duplicate confirmation safe');
denied(fn()=>$service->saveTour(999,array_replace($values,['capacity'=>1]),$tour));
productCheck($service->transition(300,$booking,'cancelled'),'owner cancellation');
productCheck($service->used($tour)===0,'cancellation releases seats');
productCheck(!$service->transition(300,$booking,'cancelled'),'duplicate cancellation safe');
denied(fn()=>$service->transition(999,$booking,'confirmed',null,true));
$reject=$service->reserve(301,$tour,3,'request-three');
productCheck($service->transition(999,$reject,'rejected','Synthetic reason',true),'admin rejection');
productCheck($service->used($tour)===0,'rejection releases group seats');
productCheck((int)Database::run($pdo,'SELECT COUNT(*) FROM BookingStatusHistory WHERE booking_id=?',[$booking])->fetchColumn()===3,'append-only transition history');
$paidTour=$service->saveTour(999,array_replace($values,['price'=>250]));
$paid=$service->reserve(302,$paidTour,2,'paid-one');
productCheck((int)$service->booking(302,$paid)['amount']===500,'integer price snapshot');
denied(fn()=>$service->transition(999,$paid,'confirmed',null,true));
$service->manualPayment(999,$paid,'SYNTHETIC-REF-ONE');
productCheck(!$service->manualPayment(999,$paid,'SYNTHETIC-REF-ONE'),'manual payment idempotent');
productCheck($service->transition(999,$paid,'confirmed',null,true),'paid approval');
$other=$service->reserve(303,$paidTour,1,'paid-two');
denied(fn()=>$service->manualPayment(999,$other,'SYNTHETIC-REF-ONE'));
productCheck($service->booking(303,$other)['payment_status']==='pending','duplicate transaction rolled back');
$access->assign(999,304,'reporter');
productCheck($access->allows(304,'reports')&&!$access->allows(304,'export'),'least privilege reporter');
denied(fn()=>$service->manualPayment(304,$other,'NO-PERMISSION'));
denied(fn()=>$access->assign(304,305,'manager'));
// Notification failure must roll back the booking and payment, not lose the event.
$pdo->exec("CREATE TRIGGER synthetic_fail_outbox BEFORE INSERT ON NotificationOutbox FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic failure'");
try { $service->reserve(305,$tour,1,'rollback'); throw new RuntimeException('Expected outbox failure.'); }
catch(PDOException) { productCheck(!Database::run($pdo,'SELECT id FROM Bookings WHERE user_id=305 AND tour_id=?',[$tour])->fetchColumn(),'outbox failure rolls back booking'); }
finally { $pdo->exec('DROP TRIGGER synthetic_fail_outbox'); }
// Real multi-process overbooking race, including pre-existing read snapshots.
$race=$service->saveTour(999,$values);
$workers=[];
for($i=310;$i<318;$i++){ $pipes=[]; $process=proc_open([PHP_BINARY,__FILE__,'worker',(string)$i,(string)$race],[1=>['pipe','w'],2=>['pipe','w']],$pipes); $workers[]=[$process,$pipes]; }
$winners=0;
foreach($workers as [$process,$pipes]) { $output=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);productCheck(proc_close($process)===0&&$errors==='','concurrency worker: '.$errors);$winners+=$output==='created'?1:0; }
productCheck($winners===3&&$service->used($race)===3,'no overbooking across eight processes');
productCheck((int)$pdo->query("SELECT COUNT(*) FROM NotificationOutbox WHERE status='pending'")->fetchColumn()>0,'durable queued notifications');
fwrite(STDOUT,"Product integration tests passed ($checks checks).\n");
