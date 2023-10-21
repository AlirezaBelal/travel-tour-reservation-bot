<?php

require 'config.php';
require 'txt.php';

date_default_timezone_set('Asia/Tehran');

if($chatId && in_array($chatId, ADMINS)){

    if(preg_match("/^[\/\#\!]?(AddTopic) (.*)$/i", $text)){
        preg_match("/^[\/\#\!]?(AddTopic) (.*)$/i", $text, $text);
        $query = $text[2];
        
        $Groupsid = 0;
        $db->select('Topics', 'Groups');
        $columnCount = $db->count('Topics');
        $columnCount = $columnCount+1;
        if (!$db->has('Topics', ['id' => $columnCount])){
            $db->query("ALTER TABLE Data ADD topic$columnCount VARCHAR(255)");
            $db->insert('Topics', ['id' => $columnCount , 'Name' => $query,'Groups' => $Groupsid]);
            $db->update('Data', ['step' => "defult"], ["UserId" => $chatId]);
            $T = "Sucess";
        }else{
            $T = "False";
        }


        return sendMessage($chatId,"$T");

    }
    
    
    elseif ($text === 'Topics') {
        $Topics = $db->select('Topics', '*');

        $keyboard = array();
        $n = $m = 0;
        $perline = 2;

        foreach ($Topics as $button){
            $id = $button['id'];
            $Name = $button['Name'];
            $Gp = $button['Groups'];
            $callback = $Gp;
            if ((is_array($perline) && $perline[$m] == $n) || $n == $perline){
                    $m++;
                    $n = 0;
                }
            
            if($callback == '0'){
                $keyboard[$m][] = ['text'=>$Name,'callback_data'=>"sub-$id"];
                $keyboard[$m][] = ['text'=>"🗑",'callback_data'=>"delete-$id"];
                $n++;
                $n++;
            }

        }
        
        if ($n <= 1){
            $keyboard[$m][] = ['text'=>"Add Topic",'callback_data'=>"AddTopic-0"];
            $keyboard[$m+1][] = ['text'=>"BackHome",'callback_data'=>"BackHome"];
        }
        else{
            $m++;
            $n = 0;
            $keyboard[$m][] = ['text'=>"Add Topic",'callback_data'=>"AddTopic-0"];
            $keyboard[$m+1][] = ['text'=>"BackHome",'callback_data'=>"BackHome"];

        }
        

        $keyboard = json_encode(['inline_keyboard' => $keyboard, 'resize_keyboard' => true]);
        return $CV = sendkeyboard($chatId,'$textm',$keyboard);
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
        $X = file_get_contents("https://mrmd80.site/python/xx");
        sendMessage($chatId,"`Robot ping : $ping ms`\n$X");
    }
    
    elseif($text == 'usage'){
        $memory = memory_get_usage(true)/1024/1024;
        sendMessage($chatId, "`Memory usage is ".$memory." Mb`");

    }
    
    elseif (strpos($data,'send') !== false){
        $db->update('Data', ['step' => $data], ["UserId" => $chatId]);
        return sendMessage($chatId, "$reqForward");
        // editMessageText($chatId,$messageId,$reqForward);
    }
    
    elseif (strpos($data,'sub') !== false){
        
        $keyboard = array();
        $n = $m = 0;
        $perline = 2;
        
        $data = explode("-",$data);
        $id = $data[1];
        $topics = $db->select('Topics', ['Name','id','caption'], [
            'Groups' => $id
        ]);
        $caption = "";

        if(count($topics)<1){
            $keyboard[$m][] = ['text'=>"Add Topic",'callback_data'=>"AddTopic-$id"];
            $keyboard[$m+1][] = ['text'=>"BackHome",'callback_data'=>"BackHome"];

            $caption = "...";

        }else{
            foreach($topics as $Topic){
                $id2= $Topic['id'];
                $Name = $Topic['Name'];
                $cap = $Topic['caption']??"";
                $caption .= "$Name : $cap\n\n";

                if ((is_array($perline) && $perline[$m] == $n) || $n == $perline){
                        $m++;
                        $n = 0;
                    }
                    $keyboard[$m][] = ['text'=>$Name,'callback_data'=>"sub-$id2"];
                    $keyboard[$m][] = ['text'=>"🗑",'callback_data'=>"delete-$id2"];

                    $n++;
                    $n++;
    
            }
            $keyboard[$m+1][] = ['text'=>"Add Topic",'callback_data'=>"AddTopic-$id"];
            $keyboard[$m+2][] = ['text'=>"BackHome",'callback_data'=>"BackHome"];

        }

        $keyboard = json_encode(['inline_keyboard' => $keyboard, 'resize_keyboard' => true]);
        return editMessageKeyboard($chatId,$messageId,$caption,$keyboard,null);

    }

    elseif (strpos($data,'AddTopic-') !== false){
        $db->update('Data', ['step' => "$data"], ["UserId" => $chatId]);
        return sendMessage($chatId,"$getTopic");
    }
    

}


if($text or isset($photo) or isset($document) or isset($video) or isset($message['contact'])){
    
    if (strpos($text,'/start') !== false){
        
        hasdb($chatId);
        return sendMessage($chatId,"$start");
    }

    // elseif ($text == '/list') {
    //     if ($db->has('Data', ['UserId' => "$chatId"])){
    //         $checker = $db->get('Data', 'profile', ['UserId' => $chatId]);
    //         if($checker == "true"){
    //             $Topics = $db->select('Topics', '*');
    //             $perline = 2;
    //             $keyboard = array();
    //             $n = $m = 0;
    //             $TEXT = "";
                
    //             foreach ($Topics as $Topic){
    //                 $callback = $Topic['id'];
    //                 $text = $Topic['Name'];
    //                 $check = $db->get('Data', $callback, ['UserId' => $chatId]);
                    
    //                 if($check == "verify"){
    //                     $TEXT = "✅";
    //                 }elseif($check == "check"){
    //                     $TEXT = "🔄";
        
    //                 }else{
    //                     $TEXT = "❌";
    //                 }
                    
    //                 if ((is_array($perline) && $perline[$m] == $n) || $n == $perline){
    //                     $m++;
    //                     $n = 0;
    //                 }
    //                 $keyboard[$m][] = ['text'=>$text,'callback_data'=>"join$callback"];
    //                 $keyboard[$m][] = ['text'=>"$TEXT",'callback_data'=>"join$callback"];
            
    //                 $n++;
    //                 $n++;
    //             }
    //             $keyboard = json_encode(['inline_keyboard' => $keyboard, 'resize_keyboard' => true]);
    //             sendkeyboard($chatId,$start,$keyboard);
    //         }else{
    //             sendMessage($chatId,"$profileFalse");
    //         }
    //     }else{
    //         $db->insert('Data', ['UserId' => "$chatId"]);
    //         sendMessage($chatId,"$start");
    //     }
    // }

    elseif ($text == '/list') {
        if ($db->has('Data', ['UserId' => "$chatId"])){

            $checker = $db->get('Data', 'profile', ['UserId' => $chatId]);
            if($checker == "true"){
                $perline = 2;
                $keyboard = array();
                $n = $m = 0;

                $Topics = $db->select('Topics', [
                    'id',
                    'Name',
                    'Groups'
                ], [
                    'Groups' => 0
                ]);
                
                foreach ($Topics as $button){
                    
                    $id = $button['id'];
                    $Name = $button['Name'];
                    $callback = $button['Groups'];

                    $check = $db->get('Data', "topic$id", ['UserId' => $chatId]);

                    if($check == "verify"){
                        $check = "✅";
                    }elseif($check == "check"){
                        $check = "🔄";
        
                    }else{
                        $check = "❌";
                    }
                    if ((is_array($perline) && $perline[$m] == $n) || $n == $perline){
                            $m++;
                            $n = 0;
                        }
                    
                        $keyboard[$m][] = ['text'=>$Name,'callback_data'=>"join-$id"];
                        $keyboard[$m][] = ['text'=>"$check",'callback_data'=>"join-$id"];

                        $n++;
                        $n++;

                }

                $keyboard = json_encode(['inline_keyboard' => $keyboard, 'resize_keyboard' => true]);
                sendkeyboard($chatId,'$textm',$keyboard);
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
            
            if(strpos($step,"AddTopic-")!== false){
                $Groupsid = str_replace("AddTopic-","",$step);
                $db->select('Topics', 'Groups');
                $columnCount = $db->max('Topics', 'id');

                $columnCount = $columnCount+1;
                
                if (!$db->has('Topics', ['id' => $columnCount])){
                    $db->query("ALTER TABLE Data ADD topic$columnCount VARCHAR(255)");
                    $T = "Sucess";
                    $db->insert('Topics', ['id' => $columnCount , 'Name' => $text,'Groups' => $Groupsid]);
                    $db->update('Data', ['step' => "defult"], ["UserId" => $chatId]);
                }else{
                    $T = "False";
                }

                $db->update('Data', ['step' => "caption-$columnCount"], ["UserId" => $chatId]);
                return sendMessage($chatId,"$captionTopic");
            }            
            
            if(strpos($step,"caption-")!== false){
                $captionid = str_replace("caption-","",$step);

                if ($db->has('Topics', ['id' => $captionid])){
                    $db->update('Data', ['step' => "defult"], ["UserId" => $chatId]);
                    $db->update('Topics', ['caption' => "$text"], ['id' => $captionid]);
                    $T = "Sucess";

                }else{
                    $T = "False";
                }

                return sendMessage($chatId,"$T");
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
                        $check = preg_match('/(98).*/', $phone_number, $output_array);
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

# قضیه اینه وقتی مزنه رو دکمه 1 باید دکمه یک وزیر مجموعه ها بررسی بشن و به کاربر اطلاع داده بشن

if (strpos($data,'join-') !== false){
    $keyboard = array();
    $n = $m = 0;
    $perline = 2;
    $caption = "";
    $data = str_replace("join-","",$data);
    if ($db->has('Data', ['UserId' => "$chatId"])){
        $Topics = $db->select('Topics', ['Name','id','caption'], ['Groups' => $data]); //Get All Topics if 'Groups' => $data
        if(count($Topics)<1){
            $checker = $db->get('Data', "topic$data", ['UserId' => $chatId]);
            if($checker !="verify" and $checker !="check"){
                $Name = $db->get('Data', 'Name', ['UserId' => $chatId]);
                $Mobile = $db->get('Data', 'Mobile', ['UserId' => $chatId]);
                $N_Code = $db->get('Data', 'N_Code', ['UserId' => $chatId]);
                $birthday = $db->get('Data', 'birthday', ['UserId' => $chatId]);
                $sex = $db->get('Data', 'sex', ['UserId' => $chatId]);
                $Topic_Name = $db->get('Topics', 'Name', ['id' => $data])??"?";
                $id = "$data";
                $TName = "";
                $ids = "$data|";
                while (True){
                    $Topic_Group = $db->get('Topics', 'Groups', ['id' => $id])??"?";
                    $Topic_Name = $db->get('Topics', 'Name', ['id' => $id])??"?";
                
                    if($Topic_Group == '0'){
                        $TName .= $Topic_Name;
                        $ids .= "$Topic_Group";
                        break;
                    }else{
                        $id = $Topic_Group;
                        $ids = $ids."$Topic_Group|";
                        $TName .= $Topic_Name." 👉 ";
                    }
                }
                $TXT = "new request!\n\nTopic : $TName \nname : $Name\nmobile : $Mobile\nN_Code : $N_Code\nbirthday : $birthday\nsex : $sex\nid : $chatId\nusername : @$userName";
                $keyboard = [[['text'=>'تایید ✅','callback_data'=>"taeed;;$chatId;;topic$data;;$ids"]]];
                $keyboard = json_encode(['inline_keyboard' => $keyboard, 'resize_keyboard' => true]);
                
                sendkeyboard(ADMINS[0],"$TXT",$keyboard);
                
                foreach(ADMINS as $admin){
                    if ((int)$admin !== (int)ADMINS[0]) {
                        sendMessage($admin,$TXT);
                        sendMessage(ADMINS[0],$TXT);
                    }
                }

                $db->update('Data', ["topic$data" => "check"], ["UserId" => $chatId]);
                sendMessage($chatId, "$reqJoin");

            }
        }
        else{
            $caption = "";
            foreach($Topics as $Topic){
                if ((is_array($perline) && $perline[$m] == $n) || $n == $perline){
                        $m++;
                        $n = 0;
                    }

                $TopicId = $Topic['id'];
                $subsettopics = $db->select('Topics', ['Name','id','caption'], ['Groups' => $TopicId]); //search subsettopics
                $Name = $Topic['Name'];
                $caption = "$caption \n$Name : ".$Topic['caption'];
                if(count($subsettopics)<1){
                    $check = $db->get('Data', "topic$TopicId", ['UserId' => $chatId]);
                    // sendMessage($chatId, "ridi '$Name'=> '$TopicId' --> $check");

                    if($check == "verify"){
                        $check = "✅";
                    }elseif($check == "check"){
                        $check = "🔄";
        
                    }else{
                        $check = "❌";
                    }
                    
                    $keyboard[$m][] = ['text'=>$Name,'callback_data'=>"join-$TopicId"];
                    $keyboard[$m][] = ['text'=>$check,'callback_data'=>"join-$TopicId"];
                    $n++;
                    $n++;

                }
                else{

                    $check = "↗️";
                    
                    $keyboard[$m][] = ['text'=>$Name,'callback_data'=>"join-$TopicId"];
                    $keyboard[$m][] = ['text'=>$check,'callback_data'=>"join-$TopicId"];
                    $n++;
                    $n++;
                }
            }
            
            if ($n <= 1){
                $keyboard[$m][] = ['text'=>"BackToHome",'callback_data'=>"BackToHome"];
            }else{
                $keyboard[$m+1][] = ['text'=>"BackToHome",'callback_data'=>"BackToHome"];
            }
                $keyboard = json_encode(['inline_keyboard' => $keyboard, 'resize_keyboard' => true]);

                sendkeyboard($chatId,"$caption",$keyboard);

        }
    }
}


if($data == "BackHome"){
    if ($db->has('Data', ['UserId' => "$chatId"])){

    $checker = $db->get('Data', 'profile', ['UserId' => $chatId]);
    if($checker == "true"){
        // $Topics = $db->select('Topics', '*');
        $perline = 2;
        $keyboard = array();
        $n = $m = 0;

        $Topics = $db->select('Topics', [
            'id',
            'Name',
            'Groups'
        ], [
            'Groups' => 0
        ]);
        
        foreach ($Topics as $button){
            
            $id = $button['id'];
            $Name = $button['Name'];
            $callback = $button['Groups'];

            $check = $db->get('Data', "topic$id", ['UserId' => $chatId]);

            if($check == "verify"){
                $check = "✅";
            }elseif($check == "check"){
                $check = "🔄";

            }else{
                $check = "❌";
            }
            if ((is_array($perline) && $perline[$m] == $n) || $n == $perline){
                    $m++;
                    $n = 0;
                }
            
                $keyboard[$m][] = ['text'=>$Name,'callback_data'=>"join-$id"];
                $keyboard[$m][] = ['text'=>"$check",'callback_data'=>"join-$id"];

                $n++;
                $n++;

        }
        
        $keyboard = json_encode(['inline_keyboard' => $keyboard, 'resize_keyboard' => true]);
        // sendkeyboard($chatId,'$textm',$keyboard);
        return editMessageKeyboard($chatId,$messageId,'$textm',$keyboard,null);

    }else{
        sendMessage($chatId,"$profileFalse");
    }
    }else{
        $db->insert('Data', ['UserId' => "$chatId"]);
        sendMessage($chatId,"$start");
    }
}

if($data == "BackToHome"){
        if ($db->has('Data', ['UserId' => "$chatId"])){

            $checker = $db->get('Data', 'profile', ['UserId' => $chatId]);
            if($checker == "true"){
                $perline = 2;
                $keyboard = array();
                $n = $m = 0;

                $Topics = $db->select('Topics', [
                    'id',
                    'Name',
                    'Groups'
                ], [
                    'Groups' => 0
                ]);
                
                foreach ($Topics as $button){
                    
                    $id = $button['id'];
                    $Name = $button['Name'];
                    $callback = $button['Groups'];

                    $check = $db->get('Data', "topic$id", ['UserId' => $chatId]);

                    if($check == "verify"){
                        $check = "✅";
                    }elseif($check == "check"){
                        $check = "🔄";
        
                    }else{
                        $check = "❌";
                    }
                    if ((is_array($perline) && $perline[$m] == $n) || $n == $perline){
                            $m++;
                            $n = 0;
                        }
                    
                        $keyboard[$m][] = ['text'=>$Name,'callback_data'=>"join-$id"];
                        $keyboard[$m][] = ['text'=>"$check",'callback_data'=>"join-$id"];

                        $n++;
                        $n++;

                }

                $keyboard = json_encode(['inline_keyboard' => $keyboard, 'resize_keyboard' => true]);
                // sendkeyboard($chatId,'$textm',$keyboard);
                return editMessageKeyboard($chatId,$messageId,'$textm',$keyboard,null);

            }else{
                sendMessage($chatId,"$profileFalse");
            }
        }else{
            $db->insert('Data', ['UserId' => "$chatId"]);
            sendMessage($chatId,"$start");
        }
}

elseif (strpos($data,'taeed') !== false){
    $data = str_replace("taeed","",$data);
    $ex = explode(';;',$data);
    $chatid = $ex[1];
    $data = $ex[2];
    $topicIds = $ex[3];
    $topicIds = str_replace("|0","",$topicIds);
    $TopicIds = explode("|",$topicIds);
    $Names = "";
    $num = count($TopicIds);
    foreach($TopicIds as $Ids){
        $num--;
        if($num == 0){
            $Names .= $db->get('Topics', 'Name', ['id' => $Ids]);

        }else{
            $Names .= $db->get('Topics', 'Name', ['id' => $Ids])." 👈 ";
        }
    }
    sendMessage($chatid, "$topicIds");
    // sendMessage($chatid, "$Ids");

    $db->update('Data', [$data => "verify"], ["UserId" => $chatid]);
    bot('answercallbackquery',[
        'callback_query_id'=>$callbackId,
        'text'         => "با موفقیت تایید شد :))",
        'show_alert'=> true ,
    ]);
     editMessageText($chatId,$messageId,"تایید شده ✅\n\n$text2");
    $acceptTopic = "مدیریت ربات با عضویت شما در گروه $Names موافقت کرد✅";

    sendMessage($chatid, "$acceptTopic");

}

elseif (strpos($data,'delete-') !== false){
    $data = str_replace("delete-","",$data);
    if ($db->has('Topics', ["id" => $data])){
        $topics = $db->select('Topics', ['id'], [
            'Groups' => $data
        ]);
        
        if(count($topics)<1){
            $db->query("ALTER TABLE Data DROP COLUMN topic$data");
            $db->delete('Topics', ['id' => $data]);
            $T = "Delted";
        }else{
            foreach($topics as $topic){
                $topicid = $topic['id'];
                $db->query("ALTER TABLE Data DROP COLUMN topic$topicid");
                $db->delete('Topics', ['id' => $topicid]);
            }
            $db->query("ALTER TABLE Data DROP COLUMN topic$data");
            $db->delete('Topics', ['id' => $data]);
            $T = "Delted";

        }


        sendMessage($chatId,"`Hi $T`");

    }
}
 
 


