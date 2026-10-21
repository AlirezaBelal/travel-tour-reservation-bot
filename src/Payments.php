<?php
interface PaymentAdapter
{
    /** Must verify authenticity and authoritative amount/order before returning. */
    public function verify(array $callback): array;
}
/** Local signed simulator, NOT a merchant gateway or a real payment provider. */
final class SandboxPaymentAdapter implements PaymentAdapter
{
    public function __construct(private string $secret) {
        if(strlen($secret)<32) throw new InvalidArgumentException('Sandbox signing key must contain at least 32 bytes.');
    }
    public function signature(array $event):string {
        return hash_hmac('sha256',json_encode([$event['order_ref']??null,$event['transaction']??null,$event['amount']??null,$event['status']??null,$event['timestamp']??null],JSON_THROW_ON_ERROR),$this->secret);
    }
    public function verify(array $callback):array {
        if(!is_string($callback['signature']??null)||!hash_equals($this->signature($callback),$callback['signature'])
        ||!is_int($callback['timestamp']??null)||abs(time()-$callback['timestamp'])>300
        ||!is_int($callback['amount']??null)||$callback['amount']<0
        ||!is_string($callback['order_ref']??null)||!preg_match('/^[a-f0-9]{32}$/D',$callback['order_ref'])
        ||!is_string($callback['transaction']??null)||!preg_match('/^[A-Za-z0-9_-]{3,80}$/D',$callback['transaction'])
        ||!in_array($callback['status']??null,['paid','failed'],true)) {
            throw new RuleViolation('پاسخ پرداخت آزمایشی معتبر نیست.');
        }
        return $callback;
    }
}
final class PaymentService
{
    public function __construct(private PDO $pdo,private Outbox $outbox) {
    }
    public function callback(PaymentAdapter $adapter,array $payload):bool {
        $event=$adapter->verify($payload);
        return Database::atomic($this->pdo,function()use($event){
            $lookup=Database::run($this->pdo,'SELECT p.booking_id,b.tour_id FROM Payments p JOIN Bookings b ON b.id=p.booking_id WHERE p.order_ref=?',[$event['order_ref']])->fetch(PDO::FETCH_ASSOC);
            if(!$lookup) throw new RuleViolation('سفارش پرداخت پیدا نشد.');
            Database::run($this->pdo,'SELECT id FROM Tours WHERE id=? FOR UPDATE',[$lookup['tour_id']]);
            $booking=Database::run($this->pdo,'SELECT * FROM Bookings WHERE id=? FOR UPDATE',[$lookup['booking_id']])->fetch(PDO::FETCH_ASSOC);
            $payment=Database::run($this->pdo,'SELECT * FROM Payments WHERE order_ref=? FOR UPDATE',[$event['order_ref']])->fetch(PDO::FETCH_ASSOC);
            $transaction='sandbox:'.hash('sha256',$event['transaction']);
            if((int)$payment['amount']!==$event['amount']) throw new RuleViolation('مبلغ پرداخت با سفارش مطابقت ندارد.');
            if($payment['provider_tx']===$transaction && $payment['status']===$event['status']) return false;
            if($payment['status']==='paid') {
                if($payment['provider_tx']===$transaction) return false;
                throw new RuleViolation('سفارش قبلاً با تراکنش دیگری پرداخت شده است.');
            }
            if($payment['expires_at']<=gmdate('Y-m-d H:i:s')||!in_array($booking['status'],['pending','confirmed'],true)) throw new RuleViolation('سفارش پرداخت منقضی یا بسته شده است.');
            try {
                Database::run($this->pdo,'UPDATE Payments SET status=?,method=\'sandbox\',provider_tx=? WHERE id=?',[$event['status'],$transaction,$payment['id']]);
            }
            catch(PDOException $error) {
                if(($error->errorInfo[1]??null)===1062) throw new RuleViolation('تراکنش تکراری است.');
                throw $error;
            }
            if($booking['user_id']!==null) $this->outbox->message((int)$booking['user_id'],'نتیجه پرداخت آزمایشی رزرو '.$booking['id'].': '.$event['status'],'sandbox:'.$payment['id'].':'.$event['status']);
            return true;
        }
        );
    }
}
