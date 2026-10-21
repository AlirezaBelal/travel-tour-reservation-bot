<?php
set_error_handler(static function(int $severity,string $message,string $file,int $line):bool {
    if ($severity & (E_WARNING | E_NOTICE | E_USER_WARNING)) throw new ErrorException($message,0,$severity,$file,$line);
    return false;
});
require __DIR__.'/../src/product_bootstrap.php';
require_once __DIR__.'/../database/product.php';
putenv('PROFILE_ENCRYPTION_KEY='.base64_encode(str_repeat('k',32)));
$dsn=getenv('TEST_MYSQL_DSN');if(!$dsn||preg_match('/(?:^|;)dbname=travel_tour_test(?:;|$)/D',$dsn)!==1)throw new RuntimeException('Isolated test database required.');
$pdo=new PDO($dsn,getenv('TEST_MYSQL_USER')?:'root',getenv('TEST_MYSQL_PASSWORD')?:'');Database::configure($pdo);migrateTravelerIdentity($pdo);migrateTravelerIdentity($pdo);$access=new AccessControl($pdo,[999]);$outbox=new Outbox($pdo);$service=new BookingService($pdo,$access,$outbox);$bot=new ProductBot($pdo,$service,$access,$outbox);$processor=new WebhookProcessor($pdo,$service,$bot,$outbox);
$checks=0;$next=10000;
function wfCheck(bool $condition,string $label):void{global$checks;$checks++;if(!$condition)throw new RuntimeException($label);}
function sendUpdate(int $actor,?string $text=null,?string $data=null,?int $id=null,?array $contact=null):int{global$processor,$next;$id??=++$next;$processor->handle($id,$actor,$text,$data,null,null,$contact);return$id;}
function lastReply(int $actor):array{global$pdo;return json_decode(Database::run($pdo,'SELECT payload FROM NotificationOutbox WHERE chat_id=? ORDER BY id DESC LIMIT 1',[$actor])->fetchColumn(),true,512,JSON_THROW_ON_ERROR);}
sendUpdate(500,'/start');
$sessionStore = new BotSessionStore($pdo);
$sessionStore->put(500, 'synthetic_session', ['source' => 'test']);
wfCheck($sessionStore->require(500, 'synthetic_session')['source'] === 'test', 'session storage round trip');
try {
    $sessionStore->require(500, 'other_session');
    throw new RuntimeException('Unrelated form was accepted.');
} catch (RuleViolation $error) {
    wfCheck(true, 'session state boundary enforced');
}
$sessionStore->clear(500);
wfCheck($sessionStore->get(500) === null, 'session clear removes form state');
Database::run($pdo, "INSERT INTO BotSessions(user_id,state,payload) VALUES (500,'unknown_synthetic_state','{}') ON DUPLICATE KEY UPDATE state='unknown_synthetic_state',payload='{}'");
sendUpdate(500, 'Synthetic unsupported form');
wfCheck(str_contains(lastReply(500)['text'], 'برای ادامه از دکمه تأیید'), 'unknown form state fails closed');
sendUpdate(500, null, 'p:does_not_exist:0');
wfCheck(str_contains(lastReply(500)['text'], 'این دکمه دیگر معتبر نیست'), 'unknown product callback fails closed');
sendUpdate(500, '/cancel');
sendUpdate(500,null,'p:profile:0');sendUpdate(500,null,'p:name:0');sendUpdate(500,'Synthetic New Traveler');wfCheck(Database::run($pdo,'SELECT Name FROM Data WHERE UserId=500')->fetchColumn()==='Synthetic New Traveler','minimal profile onboarding');wfCheck(Database::run($pdo,'SELECT N_Code FROM Data WHERE UserId=500')->fetchColumn()===null,'national code not collected');
// Verify own-contact keyboard and encrypted identity storage without using any real personal data.
sendUpdate(510,'/start');sendUpdate(510,null,'p:name:0');sendUpdate(510,'Synthetic Consent');
$share=lastReply(510);$keyboard=json_decode($share['reply_markup'],true);
wfCheck(($keyboard['keyboard'][0][0]['request_contact']??false)===true,'Telegram own-contact request button is shown');
sendUpdate(510,'09121234567');
wfCheck(Database::run($pdo,'SELECT state FROM BotSessions WHERE user_id=510')->fetchColumn()==='phone','typed phone rejected and form retained');
sendUpdate(510,null,null,null,['phone_number'=>'09121234567','user_id'=>511]);
wfCheck(Database::run($pdo,'SELECT state FROM BotSessions WHERE user_id=510')->fetchColumn()==='phone','third party contact denied');
sendUpdate(510,null,null,null,['phone_number'=>'+989121234567','user_id'=>510]);
wfCheck(Database::run($pdo,'SELECT state FROM BotSessions WHERE user_id=510')->fetchColumn()==='national','verified shared phone advances to national code');
sendUpdate(510,'invalid');
wfCheck(Database::run($pdo,'SELECT state FROM BotSessions WHERE user_id=510')->fetchColumn()==='national','bad national checksum rejected');
sendUpdate(510,'0000000019');
$identity=new TravelerIdentity($pdo);
wfCheck($identity->complete(510),'encrypted profile complete after valid code');
$encoded=Database::run($pdo,'SELECT mobile_cipher,national_cipher FROM TravelerIdentity WHERE user_id=?',[510])->fetch(PDO::FETCH_ASSOC);
wfCheck(strpos($encoded['mobile_cipher'],'09121234567')===false && strpos($encoded['national_cipher'],'0000000019')===false,'identifiers are not persisted in plaintext');
wfCheck(Database::run($pdo,'SELECT Mobile FROM Data WHERE UserId=?',[510])->fetchColumn()===null && Database::run($pdo,'SELECT N_Code FROM Data WHERE UserId=?',[510])->fetchColumn()===null,'new onboarding avoids legacy plaintext columns');
sendUpdate(510,'/profile');wfCheck(!str_contains(lastReply(510)['text'],'0000000019') && str_contains(lastReply(510)['text'],'ثبت شد'),'profile never echoes identity values');

// The old command must use the encrypted path; old in-flight input must be discarded.
sendUpdate(512,'/setprofile@SyntheticBot');
wfCheck(Database::run($pdo,'SELECT step FROM Data WHERE UserId=512')->fetchColumn()==='defult','setprofile aliases secure profile');
sendUpdate(512,null,'p:name:0');sendUpdate(512,'Synthetic Alias');
sendUpdate(512,null,null,null,['phone_number'=>'09121234567','user_id'=>512]);sendUpdate(512,'0000000019');
wfCheck($identity->complete(512),'legacy command leads to encrypted onboarding');
foreach(['name','contact','NationalCode','sex','birthday']as$oldStep){
 Database::run($pdo,'UPDATE Data SET step=?,Mobile=?,N_Code=? WHERE UserId=512',[$oldStep,'synthetic-old-mobile','synthetic-old-code']);
 sendUpdate(512,'Synthetic discarded input');
 $old=Database::run($pdo,'SELECT step,Mobile,N_Code,Name FROM Data WHERE UserId=512')->fetch(PDO::FETCH_ASSOC);
 wfCheck($old['step']==='defult'&&$old['Mobile']==='synthetic-old-mobile'&&$old['N_Code']==='synthetic-old-code'&&$old['Name']==='Synthetic Alias','stale state discarded without rewriting old data: '.$oldStep);
 wfCheck(!str_contains(lastReply(512)['text'],'Synthetic discarded input'),'stale input absent from outbox');
}
// Check actual authenticated encryption and randomized nonces with synthetic values only.
foreach(['mobile_cipher'=>'+989121234567','national_cipher'=>'0000000019']as$field=>$expected){
 $cipher=$encoded[$field];$nonce=substr($cipher,0,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
 wfCheck(sodium_crypto_secretbox_open(substr($cipher,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),$nonce,str_repeat('k',32))===$expected,'authenticated ciphertext roundtrip: '.$field);
}
$identity->saveNational(510,'0000000019');
wfCheck(Database::run($pdo,'SELECT national_cipher FROM TravelerIdentity WHERE user_id=510')->fetchColumn()!==$encoded['national_cipher'],'repeated identifier uses fresh nonce');
putenv('PROFILE_ENCRYPTION_KEY=');
try{$identity->saveNational(510,'0000000019');throw new RuntimeException('Missing key accepted');}catch(RuleViolation $error){wfCheck($identity->complete(510),'missing key fails closed without deleting existing identity');}
putenv('PROFILE_ENCRYPTION_KEY='.base64_encode(str_repeat('k',32)));

sendUpdate(512,'/privacy');sendUpdate(512,null,'p:erase:0');sendUpdate(512,null,'p:erase_yes:0');
wfCheck(!$identity->complete(512)&&!Database::run($pdo,'SELECT user_id FROM TravelerIdentity WHERE user_id=512')->fetchColumn(),'account erasure removes encrypted identity');

// Complete guided tour form, including persistence across handler restarts.
sendUpdate(999,'/admin');sendUpdate(999,null,'p:new:0');
foreach(['=Synthetic CSV title','Synthetic Destination','Synthetic description','0',gmdate('Y-m-d H:i',time()+172800),gmdate('Y-m-d H:i',time()+180000),'0','5']as$input)sendUpdate(999,$input);
$tour=(int)Database::run($pdo,'SELECT id FROM Tours WHERE title=?',['=Synthetic CSV title'])->fetchColumn();wfCheck($tour>0&&$service->tour($tour)['publication']==='draft','guided admin tour creation');
// End time must be later than the start; reject immediately without advancing the persisted form.
$access->assign(999,998,'manager');sendUpdate(998,null,'p:new:0');
$testStart=gmdate('Y-m-d H:i',time()+172800);
foreach(['Synthetic time-order validation','Synthetic destination','Synthetic date-check description','0',$testStart]as$input)sendUpdate(998,$input);
wfCheck(Database::run($pdo,'SELECT state FROM BotSessions WHERE user_id=998')->fetchColumn()==='tour_ends_at','end-date prompt reached');
sendUpdate(998,$testStart);
wfCheck(Database::run($pdo,'SELECT state FROM BotSessions WHERE user_id=998')->fetchColumn()==='tour_ends_at','invalid same-time end keeps end-date form');
wfCheck(str_contains(lastReply(998)['text'],'بعد از زمان شروع'),'time-order rejection explains what to correct');
sendUpdate(998,gmdate('Y-m-d H:i',time()+180000));
wfCheck(Database::run($pdo,'SELECT state FROM BotSessions WHERE user_id=998')->fetchColumn()==='tour_price','corrected end advances to price');
sendUpdate(998,'0');sendUpdate(998,'3');
wfCheck((bool)Database::run($pdo,'SELECT id FROM Tours WHERE title=?',['Synthetic time-order validation'])->fetchColumn(),'tour saves after correcting end date');
sendUpdate(999,null,"p:publish:$tour");wfCheck($service->tour($tour)['publication']==='published','publication workflow');
putenv('REQUIRE_VERIFIED_PROFILE=true');
try{$service->reserve(500,$tour,1,'identity-missing');throw new RuntimeException('Unverified profile was accepted');}catch(RuleViolation $error){wfCheck(str_contains($error->getMessage(),'کد ملی'),'strict onboarding gate denies unverified identity');}
putenv('REQUIRE_VERIFIED_PROFILE=false');
sendUpdate(500,'/search');sendUpdate(500,'Synthetic Destination');$reply=lastReply(500);wfCheck(isset($reply['reply_markup']),'search result buttons');sendUpdate(500,null,"p:tour:$tour");wfCheck(str_contains(lastReply(500)['text'],'باقی‌مانده'),'capacity visible');
sendUpdate(500,null,"p:reserve:$tour");sendUpdate(500,'۲');$reply=lastReply(500);$keyboard=json_decode($reply['reply_markup'],true);$confirmation=$keyboard['inline_keyboard'][0][0]['callback_data'];wfCheck(strlen($confirmation)<=64,'bounded booking confirmation');
$delivery=sendUpdate(500,null,$confirmation);$booking=(int)Database::run($pdo,'SELECT id FROM Bookings WHERE user_id=500 AND tour_id=?',[$tour])->fetchColumn();wfCheck($booking>0&&$service->used($tour)===2,'group booking through Telegram');$count=(int)$pdo->query('SELECT COUNT(*) FROM NotificationOutbox')->fetchColumn();sendUpdate(500,null,$confirmation,$delivery);wfCheck((int)$pdo->query('SELECT COUNT(*) FROM NotificationOutbox')->fetchColumn()===$count,'duplicate webhook has no side effects');
sendUpdate(501,null,"p:booking:$booking");wfCheck(str_contains(lastReply(501)['text'],'تعلق ندارد'),'cross-user read rejected');sendUpdate(501,null,"p:confirm:$booking");wfCheck($service->booking(500,$booking)['status']==='pending','forged admin callback rejected');
sendUpdate(999,null,'p:pending:0');sendUpdate(999,null,"p:review:$booking");sendUpdate(999,null,"p:confirm:$booking");wfCheck($service->booking(500,$booking)['status']==='confirmed','admin confirmation workflow');
sendUpdate(500,'/bookings');sendUpdate(500,null,"p:history:$booking");wfCheck(str_contains(lastReply(500)['text'],'تأییدشده'),'booking history visible');sendUpdate(500,null,"p:cancel_booking:$booking");wfCheck($service->used($tour)===2,'first cancel click does not cancel');sendUpdate(500,null,"p:cancel_yes:$booking");wfCheck($service->used($tour)===0,'confirmed user cancellation releases group capacity');
// RBAC and CSV formula neutralization, with no profile data columns.
sendUpdate(999,null,'p:roles:0');sendUpdate(999,'502');sendUpdate(999,'مدیر');wfCheck($access->role(502)===null,'role change awaits confirmation');sendUpdate(999,null,'p:role_yes:502');wfCheck($access->role(502)==='manager','owner role assignment');sendUpdate(502,null,"p:export:$tour");$document=json_decode(Database::run($pdo,"SELECT payload FROM NotificationOutbox WHERE chat_id=502 AND method='sendDocument' ORDER BY id DESC LIMIT 1")->fetchColumn(),true);wfCheck(str_contains($document['_csv'],"'=Synthetic CSV title")&&!str_contains($document['_csv'],'Synthetic New Traveler'),'CSV safe and contains no traveler name');
$access->assign(999,503,'reporter');sendUpdate(503,null,"p:export:$tour");wfCheck(str_contains(lastReply(503)['text'],'اجازه'),'reporter cannot export');
// Report queries and CSV projection are authorized independently of Telegram routing.
$reporting = new BookingReportService($pdo, $service, $access);
$summary = $reporting->summary(999, $tour);
wfCheck($summary['capacity'] === 5 && $summary['remaining'] === 5 && is_array($summary['statuses']), 'reports expose expected capacity and grouped statuses');
$csvDirect = $reporting->csv(999, $tour);
wfCheck(str_starts_with($csvDirect, "\xEF\xBB\xBFbooking_id,tour,quantity,status,amount_IRR,payment_status"), 'CSV has stable allowlisted header');
wfCheck(str_contains($csvDirect, "'=Synthetic CSV title")
    && !str_contains($csvDirect, 'Synthetic New Traveler')
    && !str_contains($csvDirect, '0000000019'), 'CSV neutralizes formulas and excludes traveler identity');
try {
    $reporting->csv(503, $tour);
    throw new RuntimeException('Reporter was allowed to export a CSV.');
} catch (RuleViolation $error) {
    wfCheck(true, 'report-only role cannot export through the service');
}

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
sendUpdate(508,null,"p:contact:$paidBooking");sendUpdate(508,'Synthetic payment coordination question');wfCheck((int)Database::run($pdo,"SELECT COUNT(*) FROM NotificationOutbox WHERE chat_id=999 AND payload LIKE '%Synthetic payment coordination question%'")->fetchColumn()===1,'traveler reaches management inside bot');
sendUpdate(502,null,"p:message:$paidBooking");sendUpdate(502,'Synthetic operator payment instructions');wfCheck((int)Database::run($pdo,"SELECT COUNT(*) FROM NotificationOutbox WHERE chat_id=508 AND payload LIKE '%Synthetic operator payment instructions%'")->fetchColumn()===1,'manager replies without exposing Telegram IDs');
sendUpdate(501,null,"p:message:$paidBooking");wfCheck(str_contains(lastReply(501)['text'],'اجازه'),'unauthorized private message blocked');
sendUpdate(502,null,'p:all_bookings:0');wfCheck(isset(lastReply(502)['reply_markup']),'confirmed and historical bookings accessible to managers');
Database::run($pdo,'UPDATE Tours SET starts_at=?,ends_at=? WHERE id=?',[gmdate('Y-m-d H:i:s',time()-7200),gmdate('Y-m-d H:i:s',time()-3600),$paidTour]);sendUpdate(502,null,"p:complete:$paidBooking");wfCheck($service->booking(508,$paidBooking)['status']==='completed','manager completes ended booking through Telegram');
// Account deletion is explicit, idempotent and does not disclose other accounts.
sendUpdate(500,null,'p:erase:0');wfCheck((bool)Database::run($pdo,'SELECT UserId FROM Data WHERE UserId=500')->fetchColumn(),'deletion confirmation required');sendUpdate(500,null,'p:erase_yes:0');wfCheck(!Database::run($pdo,'SELECT UserId FROM Data WHERE UserId=500')->fetchColumn(),'account deletion workflow');wfCheck((int)Database::run($pdo,'SELECT SUM(hits) FROM RateLimits WHERE user_id=500')->fetchColumn()>0,'account erasure does not bypass minute limit');wfCheck((int)Database::run($pdo,"SELECT COUNT(*) FROM NotificationOutbox WHERE JSON_UNQUOTE(JSON_EXTRACT(payload,'$._subject_user'))='500'")->fetchColumn()===0,'personal profile queue purged across administrator chats');
// Input errors do not advance the persisted wizard; /cancel exits it.
sendUpdate(506,null,'p:name:0');sendUpdate(506,str_repeat('x',151));wfCheck(Database::run($pdo,'SELECT state FROM BotSessions WHERE user_id=506')->fetchColumn()==='name','invalid input preserves form');sendUpdate(506,'/cancel');wfCheck(!Database::run($pdo,'SELECT state FROM BotSessions WHERE user_id=506')->fetchColumn(),'cancel clears form');
// Notification queries have their own permission boundary outside ProductBot.
$notificationAdmin = new NotificationAdminService($pdo, $access, $outbox);
$noticeOverview = $notificationAdmin->overview(999);
wfCheck(is_array($noticeOverview['statuses']) && is_array($noticeOverview['failed']),
    'notification management exposes structured status and retry list');
try {
    $notificationAdmin->overview(501);
    throw new RuntimeException('Unauthorized user accessed notification overview.');
} catch (RuleViolation $error) {
    wfCheck(true, 'notification management enforces role on direct service calls');
}

// A revoked manager must not retain authority through a pending bulk-send queue.
$access->assign(999,509,'manager');sendUpdate(509,null,'p:broadcast:0');sendUpdate(509,(string)$tour);sendUpdate(509,'Synthetic revocable broadcast');
wfCheck((int)Database::run($pdo,"SELECT COUNT(*) FROM NotificationOutbox WHERE JSON_UNQUOTE(JSON_EXTRACT(payload,'$.text'))=?",['Synthetic revocable broadcast'])->fetchColumn()===0,'bulk send awaits explicit confirmation');
sendUpdate(509,null,'p:broadcast_yes:0');
$bulk=json_decode(Database::run($pdo,"SELECT payload FROM NotificationOutbox WHERE JSON_UNQUOTE(JSON_EXTRACT(payload,'$.text'))=? LIMIT 1",['Synthetic revocable broadcast'])->fetchColumn(),true);
wfCheck(($bulk['_permission_actor']??null)===509&&($bulk['_required_permissions']??[])===['notify'],'bulk send records authorizing manager');
$access->assign(999,509,'none');$bulkSent=0;(new NotificationWorker($pdo,$access))->tick(function($method,$payload)use(&$bulkSent){if(($payload['text']??null)==='Synthetic revocable broadcast')$bulkSent++;return['ok'=>true,'code'=>200];},1000,false);
wfCheck($bulkSent===0,'role revocation suppresses pending bulk delivery');
// Rate limiting: one warning per minute, no unbounded message fan-out.
for($i=0;$i<35;$i++)sendUpdate(507,'/help');wfCheck((int)Database::run($pdo,"SELECT COUNT(*) FROM NotificationOutbox WHERE chat_id=507")->fetchColumn()===31,'rate limit bounds replies');
fwrite(STDOUT,"Product Telegram workflows passed ($checks checks).\n");
