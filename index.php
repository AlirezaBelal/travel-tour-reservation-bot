<?php

require 'config.php';
require 'txt.php';

date_default_timezone_set('Asia/Tehran');

if (isAdmin($fromId)) {

    if (is_string($text) && preg_match("/^[\/\#\!]?(AddTopic) (.*)$/i", $text)) {
        preg_match("/^[\/\#\!]?(AddTopic) (.*)$/i", $text, $text);
        $query = $text[2];

        $Groupsid = 0;
        $db->select('Topics', 'Groups');
        $columnCount = $db->count('Topics');
        $columnCount = $columnCount + 1;
        if (!$db->has('Topics', ['id' => $columnCount])) {
            $db->query("ALTER TABLE Data ADD topic$columnCount VARCHAR(255)");
            $db->insert('Topics', ['id' => $columnCount, 'Name' => $query, 'Groups' => $Groupsid]);
            $db->update('Data', ['step' => "defult"], ["UserId" => $chatId]);
            $T = "Sucess";
        } else {
            $T = "False";
        }

        return sendMessage($chatId, "$T");

    } elseif ($text === 'Topics') {
        $Topics = $db->select('Topics', '*');

        $keyboard = array();
        $n = $m = 0;
        $perline = 2;

        foreach ($Topics as $button) {
            $id = $button['id'];
            $Name = $button['Name'];
            $Gp = $button['Groups'];
            $callback = $Gp;
            if ((is_array($perline) && ($perline[$m] ?? null) == $n) || $n == $perline) {
                $m++;
                $n = 0;
            }

            if ($callback == '0') {
                $keyboard[$m][] = ['text' => $Name, 'callback_data' => "sub-$id"];
                $keyboard[$m][] = ['text' => "🗑", 'callback_data' => "delete-$id"];
                $n += 2;
            }
        }

        if ($n > 1) {
            $m++;
        }
        $keyboard[$m][] = ['text' => "Add Topic", 'callback_data' => "AddTopic-0"];
        $keyboard[$m + 1][] = ['text' => "BackHome", 'callback_data' => "BackHome"];

        $keyboard = json_encode(['inline_keyboard' => $keyboard, 'resize_keyboard' => true], JSON_UNESCAPED_UNICODE);
        return sendkeyboard($chatId, '$textm', $keyboard);

    } elseif ($text === '/send') {
        $Topics = $db->select('Topics', '*');

        $keyboard = array();
        $n = $m = 0;
        $perline = 1;
        foreach ($Topics as $Topic) {
            $callback = $Topic['id'];
            $topicText = $Topic['Name'];
            if ((is_array($perline) && ($perline[$m] ?? null) == $n) || $n == $perline) {
                $m++;
                $n = 0;
            }
            $keyboard[$m][] = ['text' => $topicText, 'callback_data' => "send$callback"];
            $n++;
        }

        $keyboard[$m + 1][] = ['text' => "ارسال به همه ", 'callback_data' => "sendAll"];
        $keyboard = json_encode(['inline_keyboard' => $keyboard, 'resize_keyboard' => true], JSON_UNESCAPED_UNICODE);
        sendkeyboard($chatId, $selectSend, $keyboard);

    } elseif ($text === 'ping') {
        sendMessage($chatId, "Bot is reachable.");

    } elseif ($text === 'usage') {
        $memory = memory_get_usage(true) / 1024 / 1024;
        sendMessage($chatId, "Memory usage is " . round($memory, 2) . " MB");

    } elseif (is_string($data) && str_starts_with($data, 'send')) {
        $db->update('Data', ['step' => $data], ["UserId" => $chatId]);
        return sendMessage($chatId, "$reqForward");

    } elseif (is_string($data) && str_starts_with($data, 'sub-')) {
        $keyboard = array();
        $n = $m = 0;
        $perline = 2;

        $parts = explode("-", $data, 2);
        $id = $parts[1] ?? '';
        if (!ctype_digit((string)$id)) {
            return null;
        }

        $topics = $db->select('Topics', ['Name', 'id', 'caption'], [
            'Groups' => $id
        ]);
        $caption = "";

        if (count($topics) < 1) {
            $keyboard[$m][] = ['text' => "Add Topic", 'callback_data' => "AddTopic-$id"];
            $keyboard[$m + 1][] = ['text' => "BackHome", 'callback_data' => "BackHome"];
            $caption = "...";
        } else {
            foreach ($topics as $Topic) {
                $id2 = $Topic['id'];
                $Name = $Topic['Name'];
                $cap = $Topic['caption'] ?? "";
                $caption .= "$Name : $cap\n\n";

                if ((is_array($perline) && ($perline[$m] ?? null) == $n) || $n == $perline) {
                    $m++;
                    $n = 0;
                }
                $keyboard[$m][] = ['text' => $Name, 'callback_data' => "sub-$id2"];
                $keyboard[$m][] = ['text' => "🗑", 'callback_data' => "delete-$id2"];
                $n += 2;
            }
            $keyboard[$m + 1][] = ['text' => "Add Topic", 'callback_data' => "AddTopic-$id"];
            $keyboard[$m + 2][] = ['text' => "BackHome", 'callback_data' => "BackHome"];
        }

        $keyboard = json_encode(['inline_keyboard' => $keyboard, 'resize_keyboard' => true], JSON_UNESCAPED_UNICODE);
        return editMessageKeyboard($chatId, $messageId, $caption, $keyboard, null);

    } elseif (is_string($data) && str_starts_with($data, 'AddTopic-')) {
        $parentId = substr($data, strlen('AddTopic-'));
        if (!ctype_digit((string)$parentId)) {
            return null;
        }
        $db->update('Data', ['step' => $data], ["UserId" => $chatId]);
        return sendMessage($chatId, "$getTopic");
    }
}

if ($text || isset($photo) || isset($document) || isset($video) || isset($message['contact'])) {

    if (is_string($text) && str_contains($text, '/start')) {
        hasdb($chatId);
        return sendMessage($chatId, "$start");

    } elseif ($text === '/list') {
        if ($db->has('Data', ['UserId' => "$chatId"])) {
            $checker = $db->get('Data', 'profile', ['UserId' => $chatId]);
            if ($checker == "true") {
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

                foreach ($Topics as $button) {
                    $id = $button['id'];
                    $Name = $button['Name'];
                    $check = $db->get('Data', "topic$id", ['UserId' => $chatId]);

                    if ($check == "verify") {
                        $check = "✅";
                    } elseif ($check == "check") {
                        $check = "🔄";
                    } else {
                        $check = "❌";
                    }

                    if ((is_array($perline) && ($perline[$m] ?? null) == $n) || $n == $perline) {
                        $m++;
                        $n = 0;
                    }

                    $keyboard[$m][] = ['text' => $Name, 'callback_data' => "join-$id"];
                    $keyboard[$m][] = ['text' => "$check", 'callback_data' => "join-$id"];
                    $n += 2;
                }

                $keyboard = json_encode(['inline_keyboard' => $keyboard, 'resize_keyboard' => true], JSON_UNESCAPED_UNICODE);
                sendkeyboard($chatId, '$textm', $keyboard);
            } else {
                sendMessage($chatId, "$profileFalse");
            }
        } else {
            $db->insert('Data', ['UserId' => "$chatId", 'step' => 'defult']);
            sendMessage($chatId, "$start");
        }

    } elseif ($text === '/setprofile') {
        $db->update('Data', ['step' => "name"], ["UserId" => $chatId]);
        return sendMessage($chatId, "$name");

    } else {
        if ($db->has('Data', ['UserId' => "$chatId"])) {
            $step = (string)$db->get('Data', 'step', ['UserId' => $chatId]);

            if (str_starts_with($step, 'send')) {
                if (!isAdmin($fromId)) {
                    $db->update('Data', ['step' => "defult"], ["UserId" => $chatId]);
                    return null;
                }

                $target = substr($step, strlen('send'));
                $db->update('Data', ['step' => "defult"], ["UserId" => $chatId]);

                if ($target === "All") {
                    $profiles = $db->select('Data', ['profile', 'UserId']);
                    foreach ($profiles as $profile) {
                        if (($profile['profile'] ?? null) === "true") {
                            copymessage($profile['UserId'], $chatId, $messageId);
                        }
                    }
                } elseif (ctype_digit($target)) {
                    $topicColumn = "topic$target";
                    $profiles = $db->select('Data', [$topicColumn, 'UserId']);
                    foreach ($profiles as $profile) {
                        if (($profile[$topicColumn] ?? null) === "verify") {
                            copymessage($profile['UserId'], $chatId, $messageId);
                        }
                    }
                }

                return sendMessage($chatId, "$forwardTrue");
            }

            if (str_starts_with($step, "AddTopic-")) {
                if (!isAdmin($fromId) || !is_string($text) || trim($text) === '') {
                    return null;
                }

                $Groupsid = substr($step, strlen("AddTopic-"));
                if (!ctype_digit((string)$Groupsid)) {
                    $db->update('Data', ['step' => "defult"], ["UserId" => $chatId]);
                    return null;
                }

                $columnCount = ((int)$db->max('Topics', 'id')) + 1;
                if (!$db->has('Topics', ['id' => $columnCount])) {
                    $db->query("ALTER TABLE Data ADD topic$columnCount VARCHAR(255)");
                    $T = "Sucess";
                    $db->insert('Topics', ['id' => $columnCount, 'Name' => trim($text), 'Groups' => $Groupsid]);
                    $db->update('Data', ['step' => "defult"], ["UserId" => $chatId]);
                } else {
                    $T = "False";
                }

                $db->update('Data', ['step' => "caption-$columnCount"], ["UserId" => $chatId]);
                return sendMessage($chatId, "$captionTopic");
            }

            if (str_starts_with($step, "caption-")) {
                if (!isAdmin($fromId) || !is_string($text)) {
                    return null;
                }

                $captionid = substr($step, strlen("caption-"));
                if (ctype_digit((string)$captionid) && $db->has('Topics', ['id' => $captionid])) {
                    $db->update('Data', ['step' => "defult"], ["UserId" => $chatId]);
                    $db->update('Topics', ['caption' => $text], ['id' => $captionid]);
                    $T = "Sucess";
                } else {
                    $T = "False";
                }

                return sendMessage($chatId, "$T");
            }

            switch ($step) {
                case "name":
                    if (!is_string($text) || trim($text) === '') {
                        sendMessage($chatId, "$name");
                        break;
                    }

                    $db->update('Data', ['Name' => trim($text)], ["UserId" => $chatId]);
                    $keyboard = json_encode([
                        'keyboard' => [
                            [['text' => "ارسال شماره", 'request_contact' => true]],
                        ],
                        'resize_keyboard' => true,
                    ], JSON_UNESCAPED_UNICODE);
                    sendkeyboard($chatId, $contact, $keyboard);
                    $db->update('Data', ['step' => "contact"], ["UserId" => $chatId]);
                    break;

                case "contact":
                    if (isset($message['contact'])) {
                        $contactPayload = $message['contact'];
                        $phoneNumber = (string)($contactPayload['phone_number'] ?? '');
                        $contactUserId = $contactPayload['user_id'] ?? null;

                        if (isIranianMobileNumber($phoneNumber) && (int)$contactUserId === (int)$chatId) {
                            $db->update('Data', ['step' => "NationalCode"], ["UserId" => $chatId]);
                            $db->update('Data', ['Mobile' => $phoneNumber], ["UserId" => $chatId]);
                            $T = $contact_true;
                        } else {
                            $T = $contact_false;
                        }
                    } else {
                        $T = $contact2;
                        $keyboard = json_encode([
                            'keyboard' => [
                                [['text' => "ارسال شماره", 'request_contact' => true]],
                            ],
                            'resize_keyboard' => true,
                        ], JSON_UNESCAPED_UNICODE);
                        return sendkeyboard($chatId, $T, $keyboard);
                    }

                    bot('sendMessage', [
                        'chat_id' => $chatId,
                        'text' => "$T",
                        'reply_markup' => json_encode(['remove_keyboard' => true]),
                    ]);
                    break;

                case "NationalCode":
                    if (is_string($text) && isValidIranianNationalCode($text)) {
                        $db->update('Data', ['step' => "sex"], ["UserId" => $chatId]);
                        $db->update('Data', ['N_Code' => $text], ["UserId" => $chatId]);
                        $keyboard = json_encode([
                            'keyboard' => [
                                [['text' => "دختر"], ['text' => "پسر"]],
                            ],
                            'resize_keyboard' => true,
                        ], JSON_UNESCAPED_UNICODE);
                        sendkeyboard($chatId, $NationalCodeTrue, $keyboard);
                    } else {
                        sendMessage($chatId, "$NationalCodeFalse");
                    }
                    break;

                case "sex":
                    if ($text === "پسر" || $text === "دختر") {
                        $db->update('Data', ['step' => "birthday"], ["UserId" => $chatId]);
                        $db->update('Data', ['sex' => $text], ["UserId" => $chatId]);
                        bot('sendMessage', [
                            'chat_id' => $chatId,
                            'text' => "$sexTrue",
                            'reply_markup' => json_encode(['remove_keyboard' => true]),
                        ]);
                    } else {
                        sendMessage($chatId, "$sexFalse");
                    }
                    break;

                case "birthday":
                    if (is_string($text) && preg_match('/^\d{4}\/(0[1-9]|1[0-2])\/(0[1-9]|1\d|2[0-9]|3[01])$/', $text)) {
                        $db->update('Data', ['step' => "defult"], ["UserId" => $chatId]);
                        $db->update('Data', ['birthday' => $text], ["UserId" => $chatId]);
                        $db->update('Data', ['profile' => "true"], ["UserId" => $chatId]);
                        $T = $birthdayTrue;
                    } else {
                        $T = $birthdayFalse;
                    }
                    sendMessage($chatId, "$T");
                    break;
            }
        }
    }
}

if (is_string($data) && str_starts_with($data, 'join-')) {
    $keyboard = array();
    $n = $m = 0;
    $perline = 2;
    $caption = "";
    $topicId = substr($data, strlen('join-'));

    if (!ctype_digit((string)$topicId)) {
        return null;
    }

    if ($db->has('Data', ['UserId' => "$chatId"])) {
        $Topics = $db->select('Topics', ['Name', 'id', 'caption'], ['Groups' => $topicId]);
        if (count($Topics) < 1) {
            $topicColumn = "topic$topicId";
            $checker = $db->get('Data', $topicColumn, ['UserId' => $chatId]);
            if ($checker != "verify" && $checker != "check") {
                $Name = $db->get('Data', 'Name', ['UserId' => $chatId]);
                $Mobile = $db->get('Data', 'Mobile', ['UserId' => $chatId]);
                $N_Code = $db->get('Data', 'N_Code', ['UserId' => $chatId]);
                $birthday = $db->get('Data', 'birthday', ['UserId' => $chatId]);
                $sex = $db->get('Data', 'sex', ['UserId' => $chatId]);

                $id = (string)$topicId;
                $TName = "";
                $ids = "$topicId|";
                $guard = 0;

                while ($guard++ < 100) {
                    $Topic_Group = $db->get('Topics', 'Groups', ['id' => $id]);
                    $Topic_Name = $db->get('Topics', 'Name', ['id' => $id]);
                    if ($Topic_Group === null || $Topic_Name === null) {
                        break;
                    }

                    if ((string)$Topic_Group === '0') {
                        $TName .= $Topic_Name;
                        $ids .= "0";
                        break;
                    }

                    $id = (string)$Topic_Group;
                    $ids .= "$Topic_Group|";
                    $TName .= $Topic_Name . " 👉 ";
                }

                $TXT = "new request!\n\nTopic : $TName \nname : $Name\nmobile : $Mobile\nN_Code : $N_Code\nbirthday : $birthday\nsex : $sex\nid : $chatId\nusername : @$userName";
                $keyboard = [[['text' => 'تایید ✅', 'callback_data' => "taeed;;$chatId;;$topicColumn;;$ids"]]];
                $keyboard = json_encode(['inline_keyboard' => $keyboard, 'resize_keyboard' => true], JSON_UNESCAPED_UNICODE);

                sendkeyboard(ADMINS[0], $TXT, $keyboard);
                foreach (ADMINS as $admin) {
                    if ((int)$admin !== (int)ADMINS[0]) {
                        sendMessage($admin, $TXT);
                    }
                }

                $db->update('Data', [$topicColumn => "check"], ["UserId" => $chatId]);
                sendMessage($chatId, "$reqJoin");
            }
        } else {
            foreach ($Topics as $Topic) {
                if ((is_array($perline) && ($perline[$m] ?? null) == $n) || $n == $perline) {
                    $m++;
                    $n = 0;
                }

                $TopicId = $Topic['id'];
                $subsettopics = $db->select('Topics', ['Name', 'id', 'caption'], ['Groups' => $TopicId]);
                $Name = $Topic['Name'];
                $caption .= "\n$Name : " . ($Topic['caption'] ?? '');

                if (count($subsettopics) < 1) {
                    $check = $db->get('Data', "topic$TopicId", ['UserId' => $chatId]);
                    if ($check == "verify") {
                        $check = "✅";
                    } elseif ($check == "check") {
                        $check = "🔄";
                    } else {
                        $check = "❌";
                    }
                } else {
                    $check = "↗️";
                }

                $keyboard[$m][] = ['text' => $Name, 'callback_data' => "join-$TopicId"];
                $keyboard[$m][] = ['text' => $check, 'callback_data' => "join-$TopicId"];
                $n += 2;
            }

            if ($n > 1) {
                $m++;
            }
            $keyboard[$m][] = ['text' => "BackToHome", 'callback_data' => "BackToHome"];
            $keyboard = json_encode(['inline_keyboard' => $keyboard, 'resize_keyboard' => true], JSON_UNESCAPED_UNICODE);
            sendkeyboard($chatId, "$caption", $keyboard);
        }
    }
}

if ($data === "BackHome") {
    if (!isAdmin($fromId)) {
        return null;
    }

    $Topics = $db->select('Topics', ['id', 'Name', 'Groups'], ['Groups' => 0]);
    $keyboard = [];
    $n = $m = 0;
    foreach ($Topics as $button) {
        if ($n == 2) {
            $m++;
            $n = 0;
        }
        $id = $button['id'];
        $Name = $button['Name'];
        $keyboard[$m][] = ['text' => $Name, 'callback_data' => "sub-$id"];
        $keyboard[$m][] = ['text' => "🗑", 'callback_data' => "delete-$id"];
        $n += 2;
    }
    if ($n > 1) {
        $m++;
    }
    $keyboard[$m][] = ['text' => "Add Topic", 'callback_data' => "AddTopic-0"];
    $keyboard[$m + 1][] = ['text' => "BackHome", 'callback_data' => "BackHome"];
    $keyboard = json_encode(['inline_keyboard' => $keyboard, 'resize_keyboard' => true], JSON_UNESCAPED_UNICODE);
    return editMessageKeyboard($chatId, $messageId, 'Topics', $keyboard, null);
}

if ($data === "BackToHome") {
    if ($db->has('Data', ['UserId' => "$chatId"])) {
        $checker = $db->get('Data', 'profile', ['UserId' => $chatId]);
        if ($checker == "true") {
            $perline = 2;
            $keyboard = array();
            $n = $m = 0;
            $Topics = $db->select('Topics', ['id', 'Name', 'Groups'], ['Groups' => 0]);

            foreach ($Topics as $button) {
                $id = $button['id'];
                $Name = $button['Name'];
                $check = $db->get('Data', "topic$id", ['UserId' => $chatId]);

                if ($check == "verify") {
                    $check = "✅";
                } elseif ($check == "check") {
                    $check = "🔄";
                } else {
                    $check = "❌";
                }

                if ((is_array($perline) && ($perline[$m] ?? null) == $n) || $n == $perline) {
                    $m++;
                    $n = 0;
                }

                $keyboard[$m][] = ['text' => $Name, 'callback_data' => "join-$id"];
                $keyboard[$m][] = ['text' => "$check", 'callback_data' => "join-$id"];
                $n += 2;
            }

            $keyboard = json_encode(['inline_keyboard' => $keyboard, 'resize_keyboard' => true], JSON_UNESCAPED_UNICODE);
            return editMessageKeyboard($chatId, $messageId, 'Tour groups', $keyboard, null);
        }

        sendMessage($chatId, "$profileFalse");
    } else {
        $db->insert('Data', ['UserId' => "$chatId", 'step' => 'defult']);
        sendMessage($chatId, "$start");
    }

} elseif (is_string($data) && str_starts_with($data, 'taeed')) {
    if (!isAdmin($fromId)) {
        return null;
    }

    $payload = str_replace("taeed", "", $data);
    $ex = explode(';;', $payload);
    if (count($ex) < 4) {
        return null;
    }

    $chatid = $ex[1];
    $topicColumn = $ex[2];
    $topicIds = str_replace("|0", "", $ex[3]);

    if (!ctype_digit((string)$chatid) || !preg_match('/^topic\d+$/', $topicColumn)) {
        return null;
    }

    $TopicIds = array_values(array_filter(explode("|", $topicIds), static fn($id) => ctype_digit((string)$id)));
    $Names = "";
    $num = count($TopicIds);
    foreach ($TopicIds as $Ids) {
        $num--;
        $topicName = (string)$db->get('Topics', 'Name', ['id' => $Ids]);
        $Names .= $num === 0 ? $topicName : $topicName . " 👈 ";
    }

    $db->update('Data', [$topicColumn => "verify"], ["UserId" => $chatid]);
    if (is_string($callbackId) && $callbackId !== '') {
        bot('answerCallbackQuery', [
            'callback_query_id' => $callbackId,
            'text' => "با موفقیت تایید شد :))",
            'show_alert' => true,
        ]);
    }

    editMessageText($chatId, $messageId, "تایید شده ✅\n\n$text2");
    $acceptTopic = "مدیریت ربات با عضویت شما در گروه $Names موافقت کرد✅";
    sendMessage($chatid, "$acceptTopic");

} elseif (is_string($data) && str_starts_with($data, 'delete-')) {
    if (!isAdmin($fromId)) {
        return null;
    }

    $topicId = substr($data, strlen('delete-'));
    if (!ctype_digit((string)$topicId)) {
        return null;
    }

    if ($db->has('Topics', ["id" => $topicId])) {
        $topics = $db->select('Topics', ['id'], ['Groups' => $topicId]);

        if (count($topics) < 1) {
            $db->query("ALTER TABLE Data DROP COLUMN topic$topicId");
            $db->delete('Topics', ['id' => $topicId]);
        } else {
            foreach ($topics as $topic) {
                $topicid = $topic['id'];
                if (ctype_digit((string)$topicid)) {
                    $db->query("ALTER TABLE Data DROP COLUMN topic$topicid");
                    $db->delete('Topics', ['id' => $topicid]);
                }
            }
            $db->query("ALTER TABLE Data DROP COLUMN topic$topicId");
            $db->delete('Topics', ['id' => $topicId]);
        }

        sendMessage($chatId, "Topic deleted.");
    }
}
