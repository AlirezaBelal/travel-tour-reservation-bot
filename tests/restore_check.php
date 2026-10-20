<?php
require '/app/bin/runtime.php';
$checks=0;
function restoreCheck(bool$condition,string$label):void{global$checks;$checks++;if(!$condition)throw new RuntimeException($label);}
restoreCheck((int)Database::run($pdo,'SELECT COUNT(*) FROM Bookings WHERE user_id=700')->fetchColumn()===2,'both synthetic bookings restored');
restoreCheck((int)Database::run($pdo,"SELECT COUNT(*) FROM Bookings WHERE user_id=700 AND status='cancelled'")->fetchColumn()===1,'cancelled group restored');
restoreCheck((int)Database::run($pdo,"SELECT SUM(quantity) FROM Bookings WHERE user_id=700 AND status IN ('pending','confirmed','completed')")->fetchColumn()===1,'occupied capacity after restore');
restoreCheck((int)Database::run($pdo,"SELECT COUNT(*) FROM Bookings b JOIN Payments p ON p.booking_id=b.id WHERE b.user_id=700 AND p.status='paid'")->fetchColumn()===2,'free and sandbox payment relationships restored');
restoreCheck((int)Database::run($pdo,'SELECT COUNT(*) FROM Bookings b LEFT JOIN Data d ON d.UserId=b.user_id WHERE b.user_id IS NOT NULL AND d.UserId IS NULL')->fetchColumn()===0,'no orphan user references');
restoreCheck((int)Database::run($pdo,'SELECT COUNT(*) FROM BookingStatusHistory h JOIN Bookings b ON b.id=h.booking_id WHERE b.user_id=700')->fetchColumn()>=4,'status histories restored');
restoreCheck((int)Database::run($pdo,"SELECT COUNT(*) FROM NotificationOutbox WHERE status='pending'")->fetchColumn()>0,'pending notifications preserved by restore');
echo"Restore relationship checks passed ($checks checks).\n";
