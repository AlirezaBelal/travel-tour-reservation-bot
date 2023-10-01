<?php
date_default_timezone_set('Asia/Tehran');
use Medoo\Medoo;

require 'medoo/autoload.php';

const TOKEN    = '6467247296:AAFBrGPKP4cnREmYv8nHUHNWBhk_1l_mufI';
const ADMINS   = [592299058];


$Db_Name = "vfvsdvco_Topic";
$Db_Username = "vfvsdvco_Topic";
$Db_Password = "iBicIo?U~M?z";

$db = new Medoo([
	'database_type' =>  'mysql',
	'server'        =>  'localhost',
	'charset'       =>  'utf8mb4',
	'collation'     =>  'utf8mb4_general_ci',
	'database_name' =>  $Db_Name,
	'username'      =>  $Db_Username,
	'password'      =>  $Db_Password
]);


//Request to telegram ?!
function bot(string $method, array $data = [])
{
	$curl = curl_init();
	curl_setopt_array($curl, [
		CURLOPT_URL            => 'https://api.telegram.org/bot' . TOKEN . '/' . $method,
		CURLOPT_POSTFIELDS     => $data,
		CURLOPT_RETURNTRANSFER => true
	]);
	$result = curl_exec($curl);
    
	if (curl_errno($curl)) {
		error_log(curl_error($curl).PHP_EOL);
        return false;
	}
    
	curl_close($curl);
	return json_decode($result, true);
}


function sendMessage($chatId,$textm){
    return bot('sendMessage',['chat_id'=>$chatId,'text'=>$textm]); 
}

function sendkeyboard($chatId,$textm,$keyboard){
    return bot('sendMessage', [
            'chat_id'      => $chatId,
            'text'         => "$textm",
            'reply_markup' => $keyboard
        ]);
}
 
function copymessage($chatId,$from_chat_id,$message_id){
    return bot('copymessage',['chat_id'=>$chatId,'from_chat_id'=>$from_chat_id,'message_id'=>$message_id]); 
}


function editMessageText($chatId,$message_id,$textm){
    return bot('editMessageText',['chat_id'=>$chatId,'message_id'=>$message_id,'text'=>$textm]); 
}

function editMessageCaption($chatId,$message_id,$textm){
    return bot('editMessageText',['chat_id'=>$chatId,'message_id'=>$message_id,'caption'=>$textm]); 
}

function sendfile($chatId,$document,$textm){
    bot('sendDocument',['chat_id'=>$chatId,'document' => $document,'caption'=>$textm,'parse_mode' => 'Markdown']); 
    }

function advancedBuild($buttons,int $perline, $json = true, $TEXT = "", $perfix = "")
{

    $keyboard = array();
    $n = $m = 0;
    
    foreach ($buttons as $button){
        $callback = $button['id'];
        $text = $button['Name'];
        if ((is_array($perline) && $perline[$m] == $n) || $n == $perline){
            $m++;
            $n = 0;
        }
        $keyboard[$m][] = ['text'=>$text,'callback_data'=>"$text"];
        $keyboard[$m][] = ['text'=>"$TEXT",'callback_data'=>$perfix."$callback"];

        $n++;
        $n++;
    }


    return $json
        ? json_encode(['inline_keyboard' => $keyboard, 'resize_keyboard' => true])
        : $keyboard;
    

}

function isValidIranianNationalCode(string $input) {
    if (!preg_match("/^\d{10}$/", $input)) {
        return 'false';
    }

    $check = (int) $input[9];
    $sum = array_sum(array_map(function ($x) use ($input) {
        return ((int) $input[$x]) * (10 - $x);
    }, range(0, 8))) % 11;

    return $sum < 2 ? $check == $sum : $check + $sum == 11;
}

function hasdb($userid){
    global $db;
    if (!$db->has('Data', ['UserId' => "$userid"])){
            $db->insert('Data', ['UserId' => "$userid"]);
        return false;
    }
    return true;
}




//handle updates
$update = json_decode(file_get_contents('php://input'), true);

$text = $chatType = $chatTitle = $userName = $firstName = $callbackId = $data = $query = $fromId = false;
if(isset($update['message']) || isset($update['edited_message'])) {
    
    $message    = $update['message'] ?? $update['edited_message'];
    $messageId  = $message['message_id'];
    $text       = $message['text'] ?? NULL;
    $photo       = $message['photo'] ?? NULL;
    $document       = $message['document'] ?? NULL;
    $video       = $message['video'] ?? NULL;
    $chat       = $message['chat'];
    $chatId     = $chat['id'];
    $chatType   = $chat['type'] ?? NULL;
    $chatTitle  = $chat['title'] ?? 'UnKnown';
    $from       = $message['from'];
    $fromId     = $from['id'];
    $firstName  = $from['first_name'];
    $userName   = $from['username'] ?? 'Empty';
}

elseif(isset($update['callback_query'])) {
    $message    = $update['callback_query']['message'] ?? null;
    $chatId     = $message['chat']['id'] ?? NULL;
    $text2       = $message['text'] ?? NULL;
    $messageId  = $message['message_id'] ?? NULL;
    $fromId     = $update['callback_query']['from']['id'];
    $data       = $update['callback_query']['data'] ?? NULL;
    $callbackId = $update['callback_query']['id'] ?? NULL;
    $from       = $update['callback_query']['from'];
    $fromId     = $from['id'];
    $firstName  = $from['first_name'];
    $userName   = $from['username'] ?? 'Empty';
    $messageId2 = $update['callback_query']['message']['message_id'];

}





