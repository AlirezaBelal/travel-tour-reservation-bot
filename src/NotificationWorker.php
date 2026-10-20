<?php

final class NotificationWorker
{
    public function __construct(private PDO $pdo, private AccessControl $access) {}

    public static function delay(int $attempt, int $retryAfter = 0): int
    {
        return max($retryAfter, min(3600, 2 ** min(12, max(1,$attempt))));
    }

    /** Transport returns {ok, code, retry_after?}; never persists raw API errors. */
    public function tick(callable $transport, int $limit = 20, bool $throttle = true): int
    {
        if ((int)$this->pdo->query("SELECT GET_LOCK('travel_tour_outbox_worker',0)")->fetchColumn() !== 1) {
            return 0;
        }
        $processed=0;
        try {
            $this->pdo->exec("UPDATE NotificationOutbox SET status='pending',locked_at=NULL WHERE status='processing' AND locked_at < UTC_TIMESTAMP()-INTERVAL 5 MINUTE");
            for($i=0;$i<$limit;$i++) {
                $row=Database::atomic($this->pdo,function()use($throttle){
                    $condition=$throttle ? " AND NOT EXISTS (SELECT 1 FROM NotificationOutbox recent WHERE recent.chat_id=o.chat_id AND recent.sent_at >= UTC_TIMESTAMP()-INTERVAL 1 SECOND)" : '';
                    $row=Database::run($this->pdo,"SELECT o.* FROM NotificationOutbox o WHERE o.status='pending' AND o.available_at<=UTC_TIMESTAMP() $condition ORDER BY o.id LIMIT 1 FOR UPDATE")->fetch(PDO::FETCH_ASSOC);
                    if($row) Database::run($this->pdo,"UPDATE NotificationOutbox SET status='processing',attempts=attempts+1,locked_at=UTC_TIMESTAMP() WHERE id=?",[$row['id']]);
                    return $row;
                });
                if(!$row) break;
                $payload=json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR);
                $obsolete=false;
                $authorized=true;
                foreach((array)($payload['_required_permissions']??[])as$permission) {
                    if(!is_string($permission)||!$this->access->allows((int)$row['chat_id'],$permission))$authorized=false;
                }
                if(!$authorized){Database::run($this->pdo,"UPDATE NotificationOutbox SET status='failed',error_code='permission_revoked',locked_at=NULL WHERE id=?",[$row['id']]);continue;}

                if(isset($payload['_export_actor']) && !$this->access->allows((int)$payload['_export_actor'],'export')) {
                    Database::run($this->pdo,"UPDATE NotificationOutbox SET status='failed',error_code='permission_revoked' WHERE id=?",[$row['id']]);
                    continue;
                }
                if(isset($payload['_reminder_booking'])) {
                    $booking=Database::run($this->pdo,"SELECT b.status,t.version,t.starts_at FROM Bookings b JOIN Tours t ON t.id=b.tour_id WHERE b.id=?",[$payload['_reminder_booking']])->fetch(PDO::FETCH_ASSOC);
                    $obsolete=!$booking || $booking['status']!=='confirmed' || (int)$booking['version']!==(int)$payload['_tour_version'] || $booking['starts_at']<=gmdate('Y-m-d H:i:s');
                }
                foreach(array_keys($payload)as$key) if(str_starts_with($key,'_')&&$key!=='_csv') unset($payload[$key]);
                try { $result=$obsolete?['ok'=>true,'code'=>0]:$transport($row['method'],$payload); }
                catch(Throwable) { $result=['ok'=>false,'code'=>0]; }
                $processed++;
                $code=(int)($result['code']??0);
                if(($result['ok']??false)===true) {
                    Database::run($this->pdo,"UPDATE NotificationOutbox SET status='sent',sent_at=UTC_TIMESTAMP(),locked_at=NULL,error_code=? WHERE id=?",[$obsolete?'obsolete':null,$row['id']]);
                } else {
                    $attempt=(int)$row['attempts']+1;
                    $retry=($code===0||$code===429||$code>=500)&&$attempt<8;
                    $delay=self::delay($attempt,max(0,min(86400,(int)($result['retry_after']??0))));
                    Database::run($this->pdo,'UPDATE NotificationOutbox SET status=?,available_at=?,locked_at=NULL,error_code=? WHERE id=?',[$retry?'pending':'failed',gmdate('Y-m-d H:i:s',time()+$delay),$code===0?'transport':"telegram_$code",$row['id']]);
                    if($code===429) {
                        Database::run($this->pdo,"UPDATE NotificationOutbox SET available_at=GREATEST(available_at,?) WHERE status='pending'",[gmdate('Y-m-d H:i:s',time()+$delay)]);
                        break;
                    }
                }
                if($throttle) usleep(50000);
            }
        } finally {
            $this->pdo->query("SELECT RELEASE_LOCK('travel_tour_outbox_worker')");
        }
        return $processed;
    }
}

final class TelegramTransport
{
    public function __construct(private string $token) {}

    public function __invoke(string $method,array $payload):array
    {
        $temporary=null;
        try {
            if(isset($payload['_csv'])) {
                $temporary=tempnam(sys_get_temp_dir(),'tour_export_');
                chmod($temporary,0600);
                file_put_contents($temporary,$payload['_csv']);
                unset($payload['_csv']);
                $payload['document']=new CURLFile($temporary,'text/csv','bookings.csv');
            }
            $curl=curl_init('https://api.telegram.org/bot'.$this->token.'/'.$method);
            curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15]);
            $body=curl_exec($curl);
            $code=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);
            curl_close($curl);
            $response=is_string($body)?json_decode($body,true):null;
            $ok=is_array($response)&&($response['ok']??false)===true&&$code>=200&&$code<300;
            // Editing an already-updated message is an idempotent success.
            if(!$ok&&$method==='editMessageText'&&str_contains((string)($response['description']??''),'message is not modified')) $ok=true;
            return ['ok'=>$ok,'code'=>is_array($response)?(int)($response['error_code']??$code):($code>=500?$code:0),'retry_after'=>(int)($response['parameters']['retry_after']??0)];
        } finally { if($temporary!==null&&is_file($temporary)) unlink($temporary); }
    }
}
