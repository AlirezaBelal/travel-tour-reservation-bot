<?php
// Explicit local simulator command; never calls a real gateway.
require __DIR__.'/runtime.php';
if($env('PAYMENT_SANDBOX_ENABLED')!=='true'){fwrite(STDERR,"Sandbox is disabled.\n");exit(1);}
if(count($argv)!==4){fwrite(STDERR,"Usage: php bin/sandbox-payment.php ORDER_REF TRANSACTION paid|failed\n");exit(1);}
$payment=Database::run($pdo,'SELECT amount FROM Payments WHERE order_ref=?',[$argv[1]])->fetch(PDO::FETCH_ASSOC);
if(!$payment){fwrite(STDERR,"Unknown synthetic order.\n");exit(1);}
$adapter=new SandboxPaymentAdapter($env('PAYMENT_SANDBOX_SECRET'));
$event=['order_ref'=>$argv[1],'transaction'=>$argv[2],'amount'=>(int)$payment['amount'],'status'=>$argv[3],'timestamp'=>time()];$event['signature']=$adapter->signature($event);
try{(new PaymentService($pdo,$outbox))->callback($adapter,$event);echo"Sandbox callback verified.\n";}catch(RuleViolation){fwrite(STDERR,"Sandbox callback rejected.\n");exit(1);}
