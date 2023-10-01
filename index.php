<?php

require_once 'config.php';
require_once 'txt.php';

date_default_timezone_set('Asia/Tehran');

// $text = (string)$text;
if($chatId && in_array($chatId, ADMINS)){
    
    if(preg_match("/^[\/\#\!]?(AddTopic) (.*)$/i", $text)){
        preg_match("/^[\/\#\!]?(AddTopic) (.*)$/i", $text, $text);
        $query = $text[2];
        $rand = rand(1,999999);
        if (!$db->has('Topics', ['id' => "topic$rand"])){
            $db->query("ALTER TABLE Data ADD topic$rand VARCHAR(255)");
            $db->insert('Topics', ['id' => "topic$rand" , 'Name' => $query]);
            $T = "Sucess";
        }else{
            $T = "False";
        }

        return sendMessage($chatId,"Hi $T");
    }
    
    elseif ($text === 'Topics') {
        $Topics = $db->select('Topics', '*');

        $keyboard = advancedBuild($Topics,2,true,"🗑");

        sendkeyboard($chatId,'$textm',$keyboard);
        
    }

    elseif ($text === '/send') {
        $Topics = $db->select('Topics', '*');


        $keyboard = array();
        $n = $m = 0;
        $perline = 1;
        foreach ($Topics as $Topic){
            $callback = $Topic['id'];
            $text = $Topic['Name'];
            if ((is_array($perline) && $perline[$m] == $n) || $n == $perline){
                $m++;
                $n = 0;
            }
            $keyboard[$m][] = ['text'=>$text,'callback_data'=>"send$callback"];

            $n++;
        }
        
        $keyboard[$m+1][] = ['text'=>"ارسال به همه ",'callback_data'=>"sendAll"];

    
        $keyboard = json_encode(['inline_keyboard' => $keyboard, 'resize_keyboard' => true]);
    


        // $keyboard = advancedBuild($Topics,2,true,"🗑");

        sendkeyboard($chatId,$selectSend,$keyboard);
        
    }

    elseif ($text === 'ping') {
        $start = microtime(true);
        $end   = microtime(true);
        $ping = round(($end - $start) * 1000, 3);
        sendMessage($chatId,"`Robot ping : $ping ms`");
    }
    
    elseif($text == 'usage'){
        $memory = memory_get_usage(true)/1024/1024;
        sendMessage($chatId, "`Memory usage is ".$memory." Mb`");

    }
    
    elseif (strpos($data,'send') !== false){
        $db->update('Data', ['step' => $data], ["UserId" => $chatId]);
        sendMessage($chatId, "$reqForward");
        // editMessageText($chatId,$messageId,$reqForward);
    }
    
}


if($text or isset($photo) or isset($document) or isset($video) or isset($message['contact'])){
    
    if (strpos($text,'/start') !== false){
        
        hasdb($chatId);
        return sendMessage($chatId,"$start");
    }

    elseif ($text == '/list') {
        if ($db->has('Data', ['UserId' => "$chatId"])){
            $checker = $db->get('Data', 'profile', ['UserId' => $chatId]);
            if($checker == "true"){
                $Topics = $db->select('Topics', '*');
                $perline = 2;
                $keyboard = array();
                $n = $m = 0;
                $TEXT = "";
                
                foreach ($Topics as $Topic){
                    $callback = $Topic['id'];
                    $text = $Topic['Name'];
                    $check = $db->get('Data', $callback, ['UserId' => $chatId]);
                    
                    if($check == "verify"){
                        $TEXT = "✅";
                    }elseif($check == "check"){
                        $TEXT = "🔄";
        
                    }else{
                        $TEXT = "❌";
                    }
                    
                    if ((is_array($perline) && $perline[$m] == $n) || $n == $perline){
                        $m++;
                        $n = 0;
                    }
                    $keyboard[$m][] = ['text'=>$text,'callback_data'=>"join$callback"];
                    $keyboard[$m][] = ['text'=>"$TEXT",'callback_data'=>"join$callback"];
            
                    $n++;
                    $n++;
                }
                $keyboard = json_encode(['inline_keyboard' => $keyboard, 'resize_keyboard' => true]);
                sendkeyboard($chatId,$start,$keyboard);
            }else{
                sendMessage($chatId,"$profileFalse");
            }
        }else{
            $db->insert('Data', ['UserId' => "$chatId"]);
            sendMessage($chatId,"$start");
        }
    }

    elseif ($text == '/setprofile') {
            $T = "$name";
            $db->update('Data', ['step' => "name"], ["UserId" => $chatId]);
            return sendMessage($chatId,"$T");
    }

    else{
        if ($db->has('Data', ['UserId' => "$chatId"])){
            $step = $db->get('Data', 'step', ['UserId' => $chatId]);
            if (strpos($step,'send') !== false){

                $step = str_replace("send","",$step);

                $db->update('Data', ['step' => "defult"], ["UserId" => $chatId]);
                if($step == "All"){
                    $profiles = $db->select('Data', ['profile','UserId']);
                    foreach($profiles as $profile){
                        $cechk = $profile['profile'];
                        $UserId = $profile['UserId'];
                        if($cechk == "true"){
                            copymessage($UserId,$chatId,$messageId);
                        }
                    }
                }else{
                    $profiles = $db->select('Data', [$step,'UserId']);
                    foreach($profiles as $profile){
                        $cechk = $profile[$step];
                        $UserId = $profile['UserId'];
                        if($cechk == "verify"){
                            copymessage($UserId,$chatId,$messageId);
                        }
                    }
                }
                return sendMessage($chatId,"$forwardTrue");

            }
            
            switch ($step) {
                case "name":
                    $db->update('Data', ['Name' => "$text"], ["UserId" => $chatId]);
                    $keyboard = json_encode([
                        'keyboard'=>[
                            [['text'=>"ارسال شماره", 'request_contact'=> true]],
                        ],
                        'resize_keyboard'=>true,
                    ]);
                    sendkeyboard($chatId,$contact,$keyboard);
                    $db->update('Data', ['step' => "contact"], ["UserId" => $chatId]);
                    break;
                
                case "contact":
                    if(isset($message['contact'])){
                          
                        $contact = $message['contact'];
                        $phone_number = $message['contact']['phone_number'];
                        $user_id = $contact['user_id'];
                        $check = preg_match('/^\+(98).*/', $phone_number, $output_array);
                        if($check && $user_id == $chatId){
                            $db->update('Data', ['step' => "NationalCode"], ["UserId" => $chatId]);
                            $db->update('Data', ['Mobile' => "$phone_number"], ["UserId" => $chatId]);
                            $T = $contact_true;
                        }else{
                            $T = $contact_false;
                        }
                    }else{
                        $T = $contact2;
                        $keyboard = json_encode([
                            'keyboard'=>[
                                [['text'=>"ارسال شماره", 'request_contact'=> true]],
                            ],
                            'resize_keyboard'=>true,
                        ]);
                        return sendkeyboard($chatId,$T,$keyboard);
                    }
                        bot('sendMessage', [
                            'chat_id' => $chatId,
                            'text'=>"$T",
                            'reply_markup'=>json_encode(
                                ['KeyboardRemove'=>[
                                ],'remove_keyboard'=>true
                            ])
                        ]);
                    break;
                
                case "NationalCode":
                    $check = isValidIranianNationalCode($text);
                    if($check == 1){
                        $db->update('Data', ['step' => "sex"], ["UserId" => $chatId]);
                        $db->update('Data', ['N_Code' => "$text"], ["UserId" => $chatId]);
                        $keyboard = json_encode([
                            'keyboard'=>[
                                [['text'=>"دختر"],['text'=>"پسر"]],
                            ],
                            'resize_keyboard'=>true,
                        ]);
                        sendkeyboard($chatId,$NationalCodeTrue,$keyboard);
                    }else{
                        sendMessage($chatId,"$NationalCodeFalse");
                    }
                    break;
                case "sex":
                    if($text == "پسر" or $text =="دختر"){
                        $db->update('Data', ['step' => "birthday"], ["UserId" => $chatId]);
                        $db->update('Data', ['sex' => "$text"], ["UserId" => $chatId]);
                        bot('sendMessage', [
                            'chat_id' => $chatId,
                            'text'=>"$sexTrue",
                            'reply_markup'=>json_encode(
                                ['KeyboardRemove'=>[
                                ],'remove_keyboard'=>true
                            ])
                        ]);
                    }else{
                        sendMessage($chatId,"$sexFalse");
                    }
                    break;
                case "birthday":
                    if(preg_match('/\d{4}\/(0[1-9]|1[0-2])\/(0[1-9]|1\d|2[0-9]|3[01])/', $text)){
                        $db->update('Data', ['step' => "defult"], ["UserId" => $chatId]);
                        $db->update('Data', ['birthday' => "$text"], ["UserId" => $chatId]);
                        $T = $birthdayTrue;
                        $db->update('Data', ['profile' => "true"], ["UserId" => $chatId]);

        
                    }else{
                        $T = $birthdayFalse;
                    }
                        sendMessage($chatId,"$T");
                    break;
              default:
                echo "No information available for that day.";
            }
        }
    }
}


if (strpos($data,'join') !== false){
    $data = str_replace("join","",$data);
    if ($db->has('Data', ['UserId' => "$chatId"])){
        $checker = $db->get('Data', $data, ['UserId' => $chatId]);
        
        if($checker !="verify" and $checker !="check"){
            $Name = $db->get('Data', 'Name', ['UserId' => $chatId]);
            $Mobile = $db->get('Data', 'Mobile', ['UserId' => $chatId]);
            $N_Code = $db->get('Data', 'N_Code', ['UserId' => $chatId]);
            $birthday = $db->get('Data', 'birthday', ['UserId' => $chatId]);
            $sex = $db->get('Data', 'sex', ['UserId' => $chatId]);
            $Topic_Name = $db->get('Topics', 'Name', ['id' => $data])??"?";
    
            $TXT = "new request!\n\nname : $Name\nmobile : $Mobile\nN_Code : $N_Code\nbirthday : $birthday\nsex : $sex\nid : $chatId\nusername : @$userName";

            $keyboard = [[['text'=>'تایید ✅','callback_data'=>"taeed$chatId-$data-$Topic_Name"]]];
            $keyboard = json_encode(['inline_keyboard' => $keyboard, 'resize_keyboard' => true]);
            sendkeyboard(ADMINS[0],"$TXT",$keyboard);
            $T = "check";
            $db->update('Data', [$data => "check"], ["UserId" => $chatId]);
            sendMessage($chatId, "$reqJoin");

        }
            // return sendMessage($chatId,"$T");
        
    }else{
        $T = "False";
    }
}

elseif (strpos($data,'taeed') !== false){
    $data = str_replace("taeed","",$data);
    $ex = explode('-',$data);
    $id = $ex[0];
    $data = $ex[1];
    $topicname = $ex[2];
    $db->update('Data', [$data => "verify"], ["UserId" => $id]);
    bot('answercallbackquery',[
        'callback_query_id'=>$callbackId,
        'text'         => "با موفقیت تایید شد :))",
        'show_alert'=> true ,
    ]);
     editMessageText($chatId,$messageId,"تایید شده ✅\n\n$text2");
    $acceptTopic = "مدیریت ربات با عضویت شما در گروه $topicname موافقت کرد✅";

    sendMessage($id, "$acceptTopic");

}

elseif (strpos($data,'topic') !== false){
    if ($db->has('Topics', ["id" => $data])){
        $db->query("ALTER TABLE Data DROP COLUMN $data");
        $db->delete('Topics', ['id' => $data]);
        $T = "Delted";
        sendMessage($chatId,"`Hi $T`");

    }
}




// }
