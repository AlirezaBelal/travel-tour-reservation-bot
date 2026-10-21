<?php

final class TelegramTransport
{
    public function __construct(private string $token) {
    }
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
        }
        finally {
            if($temporary!==null&&is_file($temporary)) unlink($temporary);
        }
    }
}
