<?php

final class Scheduler
{
    public function __construct(private PDO $pdo,private Outbox $outbox,private BookingService $bookings,private int $systemActor) {}
    public function run():void {
        Database::atomic($this->pdo,function(){
            $rows=Database::run($this->pdo,"SELECT b.id,b.user_id,t.title,t.starts_at,t.version FROM Bookings b JOIN Tours t ON t.id=b.tour_id WHERE b.status='confirmed' AND b.user_id IS NOT NULL AND t.starts_at>UTC_TIMESTAMP() AND t.starts_at<=UTC_TIMESTAMP()+INTERVAL 24 HOUR")->fetchAll(PDO::FETCH_ASSOC);
            foreach($rows as$row) $this->outbox->enqueue((int)$row['user_id'],'sendMessage',['text'=>'یادآوری تور «'.$row['title'].'»: زمان شروع را در جزئیات تور بررسی کنید.','_reminder_booking'=>$row['id'],'_tour_version'=>$row['version']],"reminder:{$row['id']}:{$row['version']}");
        });
        $expired=Database::run($this->pdo,"SELECT b.id FROM Bookings b JOIN Payments p ON p.booking_id=b.id WHERE b.status='pending' AND p.status='pending' AND p.expires_at<=UTC_TIMESTAMP() LIMIT 100")->fetchAll(PDO::FETCH_COLUMN);
        foreach($expired as$id) Database::atomic($this->pdo,function()use($id){
            // transition rechecks status under locks; concurrent manual payment wins safely.
            $booking=Database::run($this->pdo,'SELECT tour_id FROM Bookings WHERE id=?',[$id])->fetchColumn();
            Database::run($this->pdo,'SELECT id FROM Tours WHERE id=? FOR UPDATE',[$booking]);
            $payment=Database::run($this->pdo,'SELECT * FROM Payments WHERE booking_id=? FOR UPDATE',[$id])->fetch(PDO::FETCH_ASSOC);
            if($payment['status']!=='pending'||$payment['expires_at']>gmdate('Y-m-d H:i:s'))return;
            try {$this->bookings->transition($this->systemActor,(int)$id,'rejected','مهلت پرداخت پایان یافته است.',true);}catch(RuleViolation){return;}
            Database::run($this->pdo,"UPDATE Payments SET status='expired' WHERE booking_id=? AND status='pending'",[$id]);
        });
    }
}
