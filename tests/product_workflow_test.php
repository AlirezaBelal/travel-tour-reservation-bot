<?php
set_error_handler(static function(int $severity,string $message,string $file,int $line):bool {
    if ($severity & (E_WARNING | E_NOTICE | E_USER_WARNING)) throw new ErrorException($message,0,$severity,$file,$line);
    return false;
});
require __DIR__.'/../src/product_bootstrap.php';
$dsn=getenv('TEST_MYSQL_DSN');if(!$dsn||preg_match('/(?:^|;)dbname=travel_tour_test(?:;|$)/D',$dsn)!==1)throw new RuntimeException('Isolated test database required.');
$pdo=new PDO($dsn,getenv('TEST_MYSQL_USER')?:'root',getenv('TEST_MYSQL_PASSWORD')?:'');Database::configure($pdo);$access=new AccessControl($pdo,[999]);$outbox=new Outbox($pdo);$service=new BookingService($pdo,$access,$outbox);$bot=new ProductBot($pdo,$service,$access,$outbox);$processor=new WebhookProcessor($pdo,$service,$bot,$outbox);
$checks=0;$next=10000;
function wfCheck(bool $condition,string $label):void{global$checks;$checks++;if(!$condition)throw new RuntimeException($label);}
function sendUpdate(int $actor,?string $text=null,?string $data=null,?int $id=null):int{global$processor,$next;$id??=++$next;$processor->handle($id,$actor,$text,$data);return$id;}
function lastReply(int $actor):array{global$pdo;return json_decode(Database::run($pdo,'SELECT payload FROM NotificationOutbox WHERE chat_id=? ORDER BY id DESC LIMIT 1',[$actor])->fetchColumn(),true,512,JSON_THROW_ON_ERROR);}
sendUpdate(500,'/start');sendUpdate(500,null,'p:profile:0');sendUpdate(500,null,'p:name:0');sendUpdate(500,'Synthetic New Traveler');wfCheck(Database::run($pdo,'SELECT Name FROM Data WHERE UserId=500')->fetchColumn()==='Synthetic New Traveler','minimal profile onboarding');wfCheck(Database::run($pdo,'SELECT N_Code FROM Data WHERE UserId=500')->fetchColumn()===null,'national code not collected');
// Complete guided tour form, including persistence across handler restarts.
sendUpdate(999,'/admin');sendUpdate(999,null,'p:new:0');
foreach(['=Synthetic CSV title','Synthetic Destination','Synthetic description','0',gmdate('Y-m-d H:i',time()+172800),gmdate('Y-m-d H:i',time()+180000),'0','5']as$input)sendUpdate(999,$input);
$tour=(int)Database::run($pdo,'SELECT id FROM Tours WHERE title=?',['=Synthetic CSV title'])->fetchColumn();wfCheck($tour>0&&$service->tour($tour)['publication']==='draft','guided admin tour creation');
sendUpdate(999,null,"p:publish:$tour");wfCheck($service->tour($tour)['publication']==='published','publication workflow');
sendUpdate(500,'/search');sendUpdate(500,'Synthetic Destination');$reply=lastReply(500);wfCheck(isset($reply['reply_markup']),'search result buttons');sendUpdate(500,null,"p:tour:$tour");wfCheck(str_contains(lastReply(500)['text'],'باقی‌مانده'),'capacity visible');
sendUpdate(500,null,"p:reserve:$tour");sendUpdate(500,'۲');$reply=lastReply(500);$keyboard=json_decode($reply['reply_markup'],true);$confirmation=$keyboard['inline_keyboard'][0][0]['callback_data'];wfCheck(strlen($confirmation)<=64,'bounded booking confirmation');
$delivery=sendUpdate(500,null,$confirmation);$booking=(int)Database::run($pdo,'SELECT id FROM Bookings WHERE user_id=500 AND tour_id=?',[$tour])->fetchColumn();wfCheck($booking>0&&$service->used($tour)===2,'group booking through Telegram');$count=(int)$pdo->query('SELECT COUNT(*) FROM NotificationOutbox')->fetchColumn();sendUpdate(500,null,$confirmation,$delivery);wfCheck((int)$pdo->query('SELECT COUNT(*) FROM NotificationOutbox')->fetchColumn()===$count,'duplicate webhook has no side effects');
sendUpdate(501,null,"p:booking:$booking");wfCheck(str_contains(lastReply(501)['text'],'تعلق ندارد'),'cross-user read rejected');sendUpdate(501,null,"p:confirm:$booking");wfCheck($service->booking(500,$booking)['status']==='pending','forged admin callback rejected');
sendUpdate(999,null,'p:pending:0');sendUpdate(999,null,"p:review:$booking");sendUpdate(999,null,"p:confirm:$booking");wfCheck($service->booking(500,$booking)['status']==='confirmed','admin confirmation workflow');
sendUpdate(500,'/bookings');sendUpdate(500,null,"p:history:$booking");wfCheck(str_contains(lastReply(500)['text'],'تأییدشده'),'booking history visible');sendUpdate(500,null,"p:cancel_booking:$booking");wfCheck($service->used($tour)===2,'first cancel click does not cancel');sendUpdate(500,null,"p:cancel_yes:$booking");wfCheck($service->used($tour)===0,'confirmed user cancellation releases group capacity');
// RBAC and CSV formula neutralization, with no profile data columns.
sendUpdate(999,null,'p:roles:0');sendUpdate(999,'502');sendUpdate(999,'مدیر');wfCheck($access->role(502)===null,'role change awaits confirmation');sendUpdate(999,null,'p:role_yes:502');wfCheck($access->role(502)==='manager','owner role assignment');sendUpdate(502,null,"p:export:$tour");$document=json_decode(Database::run($pdo,"SELECT payload FROM NotificationOutbox WHERE chat_id=502 AND method='sendDocument' ORDER BY id DESC LIMIT 1")->fetchColumn(),true);wfCheck(str_contains($document['_csv'],"'=Synthetic CSV title")&&!str_contains($document['_csv'],'Synthetic New Traveler'),'CSV safe and contains no traveler name');
$access->assign(999,503,'reporter');sendUpdate(503,null,"p:export:$tour");wfCheck(str_contains(lastReply(503)['text'],'اجازه'),'reporter cannot export');
// Rejection with optional reason and expired/stale confirmation protection.
$tourVersion=(int)$service->tour($tour)['version'];
foreach([504,505]as$user){sendUpdate($user,null,'p:name:0');sendUpdate($user,'Synthetic Companion');sendUpdate($user,null,"p:reserve_yes:$tour:1:$tourVersion");}
$reject=(int)Database::run($pdo,'SELECT id FROM Bookings WHERE user_id=504 AND tour_id=?',[$tour])->fetchColumn();
sendUpdate(502,null,"p:reject:$reject");sendUpdate(502,'Synthetic rejection reason');wfCheck($service->booking(504,$reject)['status']==='rejected','reasoned rejection workflow');
$before=$service->used($tour);sendUpdate(505,null,"p:reserve_yes:$tour:1:0");wfCheck($service->used($tour)===$before,'stale callback does not change capacity');
// Manual payment is a separately confirmed, permission-checked Telegram flow.
$paidValues=['category_id'=>null,'title'=>'Synthetic manual payment','destination'=>'Synthetic','description'=>'Synthetic payment workflow','starts_at'=>gmdate('Y-m-d H:i:s',time()+86400),'ends_at'=>gmdate('Y-m-d H:i:s',time()+90000),'price'=>500,'capacity'=>3,'publication'=>'published'];
$paidTour=$service->saveTour(999,$paidValues);sendUpdate(508,null,'p:name:0');sendUpdate(508,'Synthetic Payer');sendUpdate(508,null,"p:reserve_yes:$paidTour:1:1");
$paidBooking=(int)Database::run($pdo,'SELECT id FROM Bookings WHERE user_id=508 AND tour_id=?',[$paidTour])->fetchColumn();
sendUpdate(502,null,"p:pay:$paidBooking");sendUpdate(502,'SYNTHETIC-UI-RECEIPT');wfCheck($service->booking(508,$paidBooking)['payment_status']==='pending','payment approval needs confirmation');
sendUpdate(502,null,"p:pay_yes:$paidBooking");sendUpdate(502,null,"p:confirm:$paidBooking");wfCheck($service->booking(508,$paidBooking)['status']==='confirmed','manual payment then booking approval');
sendUpdate(502,null,'p:all_bookings:0');wfCheck(isset(lastReply(502)['reply_markup']),'confirmed and historical bookings accessible to managers');
Database::run($pdo,'UPDATE Tours SET starts_at=?,ends_at=? WHERE id=?',[gmdate('Y-m-d H:i:s',time()-7200),gmdate('Y-m-d H:i:s',time()-3600),$paidTour]);sendUpdate(502,null,"p:complete:$paidBooking");wfCheck($service->booking(508,$paidBooking)['status']==='completed','manager completes ended booking through Telegram');
// Account deletion is explicit, idempotent and does not disclose other accounts.
sendUpdate(500,null,'p:erase:0');wfCheck((bool)Database::run($pdo,'SELECT UserId FROM Data WHERE UserId=500')->fetchColumn(),'deletion confirmation required');sendUpdate(500,null,'p:erase_yes:0');wfCheck(!Database::run($pdo,'SELECT UserId FROM Data WHERE UserId=500')->fetchColumn(),'account deletion workflow');wfCheck((int)Database::run($pdo,'SELECT SUM(hits) FROM RateLimits WHERE user_id=500')->fetchColumn()>0,'account erasure does not bypass minute limit');wfCheck((int)Database::run($pdo,"SELECT COUNT(*) FROM NotificationOutbox WHERE JSON_UNQUOTE(JSON_EXTRACT(payload,'$._subject_user'))='500'")->fetchColumn()===0,'personal profile queue purged across administrator chats');
// Input errors do not advance the persisted wizard; /cancel exits it.
sendUpdate(506,null,'p:name:0');sendUpdate(506,str_repeat('x',151));wfCheck(Database::run($pdo,'SELECT state FROM BotSessions WHERE user_id=506')->fetchColumn()==='name','invalid input preserves form');sendUpdate(506,'/cancel');wfCheck(!Database::run($pdo,'SELECT state FROM BotSessions WHERE user_id=506')->fetchColumn(),'cancel clears form');
// Rate limiting: one warning per minute, no unbounded message fan-out.
for($i=0;$i<35;$i++)sendUpdate(507,'/help');wfCheck((int)Database::run($pdo,"SELECT COUNT(*) FROM NotificationOutbox WHERE chat_id=507")->fetchColumn()===31,'rate limit bounds replies');
fwrite(STDOUT,"Product Telegram workflows passed ($checks checks).\n");
