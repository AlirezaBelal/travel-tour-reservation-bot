<?php
// Copied into /tmp of a disposable application container; never shipped publicly.
require '/app/bin/runtime.php';
$checks=0;$update=700000;
function httpCheck(bool$condition,string$label):void{global$checks;$checks++;if(!$condition)throw new RuntimeException($label);}
function postTest(string$path,array$payload,bool$secret=true):array{
 $headers=['Content-Type: application/json'];if($secret)$headers[]='X-Telegram-Bot-Api-Secret-Token: '.getenv('TELEGRAM_WEBHOOK_SECRET');
 $context=stream_context_create(['http'=>['method'=>'POST','header'=>implode("\r\n",$headers),'content'=>json_encode($payload,JSON_THROW_ON_ERROR),'ignore_errors'=>true,'timeout'=>10]]);
 $body=file_get_contents('http://127.0.0.1/'.$path,false,$context);preg_match('/\s(\d{3})\s/',$http_response_header[0],$match);return[(int)$match[1],$body];
}
function updateTest(int$actor,?string$text=null,?string$callback=null,?int$fixed=null,?array$contact=null):int{
 global$update;$id=$fixed??++$update;$chat=['id'=>$actor,'type'=>'private'];$from=['id'=>$actor,'is_bot'=>false];$message=['message_id'=>1,'from'=>$from,'chat'=>$chat];
 if($callback===null){$message['text']=$text;$payload=['update_id'=>$id,'message'=>$message];}else{$payload=['update_id'=>$id,'callback_query'=>['id'=>'synthetic-query-'.$id,'from'=>$from,'data'=>$callback,'message'=>$message]];}
 [$status]=postTest('index.php',$payload);httpCheck($status===200,'webhook request accepted');return$id;
}
$values=['category_id'=>null,'title'=>'Synthetic HTTP tour','destination'=>'Synthetic','description'=>'Synthetic HTTP end-to-end','starts_at'=>gmdate('Y-m-d H:i:s',time()+86400),'ends_at'=>gmdate('Y-m-d H:i:s',time()+90000),'price'=>0,'capacity'=>3,'publication'=>'published'];
httpCheck((int)Database::run($pdo,'SELECT COUNT(*) FROM Tours WHERE title=?',['تور نمایشی — داده ساختگی'])->fetchColumn()===1,'demo seeding is repeatable and synthetic');
$tour=$bookings->saveTour($owners[0],$values);
updateTest(700,'/start');updateTest(700,null,'p:name:0');updateTest(700,'Synthetic HTTP Traveler');updateTest(700,'/tours');
$delivery=updateTest(700,null,"p:reserve_yes:$tour:2:1");$booking=(int)Database::run($pdo,'SELECT id FROM Bookings WHERE user_id=700 AND tour_id=?',[$tour])->fetchColumn();httpCheck($booking>0&&$bookings->used($tour)===2,'HTTP group reservation');
updateTest(700,null,"p:reserve_yes:$tour:2:1",$delivery);httpCheck((int)Database::run($pdo,'SELECT COUNT(*) FROM Bookings WHERE user_id=700 AND tour_id=?',[$tour])->fetchColumn()===1,'HTTP replay safe');
updateTest(701,null,"p:confirm:$booking");httpCheck($bookings->booking(700,$booking)['status']==='pending','HTTP forged admin callback denied');
updateTest($owners[0],null,"p:confirm:$booking");httpCheck($bookings->booking(700,$booking)['status']==='confirmed','HTTP admin approval');
updateTest(700,null,"p:cancel_booking:$booking");updateTest(700,null,"p:cancel_yes:$booking");httpCheck($bookings->used($tour)===0,'HTTP cancellation frees seats');
updateTest(700,'/setprofile');httpCheck(Database::run($pdo,'SELECT step FROM Data WHERE UserId=700')->fetchColumn()==='name','legacy command routed through actual webhook');updateTest(700,'/cancel');updateTest(700,'Synthetic ignored after cancellation');httpCheck(Database::run($pdo,'SELECT Name FROM Data WHERE UserId=700')->fetchColumn()==='Synthetic HTTP Traveler','global cancel exits legacy profile form');
httpCheck((int)Database::run($pdo,"SELECT COUNT(*) FROM NotificationOutbox WHERE method='answerCallbackQuery'")->fetchColumn()>0,'callback acknowledgements queued');
// The application container never calls Telegram in CI; deliver through fake transport.
$sent=0;$firstMethod=null;(new NotificationWorker($pdo,$access))->tick(function($method)use(&$sent,&$firstMethod){$firstMethod??=$method;$sent++;return['ok'=>true,'code'=>200];},100,false);httpCheck($firstMethod==='answerCallbackQuery','callback acknowledgement dispatch priority');httpCheck($sent>0,'HTTP-created notifications dispatched via simulated transport');
$paidTour=$bookings->saveTour($owners[0],array_replace($values,['price'=>250]));$paid=$bookings->reserve(700,$paidTour,1,'http-payment');$order=$bookings->booking(700,$paid)['order_ref'];
$adapter=new SandboxPaymentAdapter(getenv('PAYMENT_SANDBOX_SECRET'));$event=['order_ref'=>$order,'transaction'=>'HTTP-SYNTHETIC-TX','amount'=>250,'status'=>'paid','timestamp'=>time()];$event['signature']=$adapter->signature($event);
[$status,$body]=postTest('payments/sandbox.php',$event,false);httpCheck($status===200&&str_contains($body,'processed'),'HTTP sandbox signature and order verification');[$status,$body]=postTest('payments/sandbox.php',$event,false);httpCheck($status===200&&str_contains($body,'duplicate'),'HTTP sandbox replay');
[$status]=postTest('payments/sandbox.php',array_replace($event,['signature'=>'forged']),false);httpCheck($status===400,'HTTP forged payment denied');
echo"HTTP end-to-end tests passed ($checks checks).\n";
