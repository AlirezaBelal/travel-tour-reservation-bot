<?php


// Retired profile forms must never receive new sensitive values, even when this
// compatibility handler is invoked directly instead of through ProductBot.
if ((is_string($text) && preg_match('/^\/setprofile(?:@[A-Za-z0-9_]+)?(?:\s.*)?$/u', $text))
    || isLegacyProfileStep(($membershipFlow->user((int)$chatId)['step'] ?? null))) {
    $topicForms->reset((int)$chatId);
    return $response->bot('sendMessage', [
        'chat_id' => $chatId,
        'text' => 'فرم قدیمی پروفایل بسته شد؛ اطلاعات این پیام ذخیره نشد. برای ثبت امن اطلاعات، /profile را بفرستید.',
        'reply_markup' => json_encode(['remove_keyboard' => true]),
    ]);
}

if (in_array((int)$fromId, $owners, true)) {

    if (is_string($text) && preg_match("/^[\/\#\!]?(AddTopic) (.*)$/i", $text)) {
        preg_match("/^[\/\#\!]?(AddTopic) (.*)$/i", $text, $text);
        $query = $text[2];

        $topicForms->createRoot((int)$fromId, (int)$chatId, $query);
        $T = 'دسته ایجاد شد.';

        return $response->sendMessage($chatId, "$T");

    } elseif ($text === 'Topics') {
        $Topics = $catalogue->all();

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
        $keyboard[$m][] = ['text' => "ایجاد دسته", 'callback_data' => "AddTopic-0"];
        $keyboard[$m + 1][] = ['text' => "بازگشت", 'callback_data' => "BackHome"];

        $keyboard = json_encode(['inline_keyboard' => $keyboard, 'resize_keyboard' => true], JSON_UNESCAPED_UNICODE);
        return $response->sendkeyboard($chatId, $list, $keyboard);

    } elseif ($text === '/send') {
        $Topics = $catalogue->all();

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
        $response->sendkeyboard($chatId, $selectSend, $keyboard);

    } elseif ($text === 'ping') {
        $response->sendMessage($chatId, "Bot is reachable.");

    } elseif ($text === 'usage') {
        $memory = memory_get_usage(true) / 1024 / 1024;
        $response->sendMessage($chatId, "Memory usage is " . round($memory, 2) . " MB");

    } elseif (is_string($data) && str_starts_with($data, 'send')) {
        $broadcasts->begin((int)$fromId, (int)$chatId, $data);
        return $response->sendMessage($chatId, "$reqForward");

    } elseif (is_string($data) && str_starts_with($data, 'sub-')) {
        $keyboard = array();
        $n = $m = 0;
        $perline = 2;

        $parts = explode("-", $data, 2);
        $id = $parts[1] ?? '';
        if (!ctype_digit((string)$id)) {
            return null;
        }

        $topics = $catalogue->children((int)$id);
        $caption = "";

        if (count($topics) < 1) {
            $keyboard[$m][] = ['text' => "ایجاد دسته", 'callback_data' => "AddTopic-$id"];
            $keyboard[$m + 1][] = ['text' => "بازگشت", 'callback_data' => "BackHome"];
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
            $keyboard[$m + 1][] = ['text' => "ایجاد دسته", 'callback_data' => "AddTopic-$id"];
            $keyboard[$m + 2][] = ['text' => "بازگشت", 'callback_data' => "BackHome"];
        }

        $keyboard = json_encode(['inline_keyboard' => $keyboard, 'resize_keyboard' => true], JSON_UNESCAPED_UNICODE);
        return $response->editMessageKeyboard($chatId, $messageId, $caption, $keyboard, null);

    } elseif (is_string($data) && str_starts_with($data, 'AddTopic-')) {
        $parentId = substr($data, strlen('AddTopic-'));
        if (!ctype_digit((string)$parentId)) {
            return null;
        }
        $topicForms->begin((int)$fromId, (int)$chatId, (int)$parentId);
        return $response->sendMessage($chatId, "$getTopic");
    }
}

if ($hasContent) {

    if (is_string($text) && preg_match('/^\/start(?:@[A-Za-z0-9_]+)?(?: [A-Za-z0-9_-]{1,64})?$/D', $text)) {
        $membershipFlow->ensureUser((int)$chatId);
        return $response->sendMessage($chatId, "$start");

    } elseif ($text === '/list') {
        if ($membershipFlow->user((int)$chatId) !== null) {
            $checker = ($membershipFlow->user((int)$chatId)['profile'] ?? null);
            if ($checker == "true") {
                $perline = 2;
                $keyboard = array();
                $n = $m = 0;

                $Topics = $catalogue->children(0);

                foreach ($Topics as $button) {
                    $id = $button['id'];
                    $Name = $button['Name'];
                    $check = $membershipFlow->status((int)$chatId, $id);

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
                $response->sendkeyboard($chatId, $list, $keyboard);
            } else {
                $response->sendMessage($chatId, "$profileFalse");
            }
        } else {
            $membershipFlow->ensureUser((int)$chatId);
            $response->sendMessage($chatId, "$start");
        }

    } else {
        if ($membershipFlow->user((int)$chatId) !== null) {
            $broadcast = $broadcasts->consume((int)$fromId, (int)$chatId);
            if ($broadcast !== null) {
                if (!$broadcast['authorized']) return null;
                foreach ($broadcast['recipients'] as $recipient) {
                    $response->copymessage($recipient, $chatId, $messageId);
                }
                return $response->sendMessage($chatId, "$forwardTrue");
            }

            $topicResult = $topicForms->submit((int)$fromId, (int)$chatId, is_string($text) ? $text : null);
            if ($topicResult !== null) {
                $response = [
                    'caption' => $captionTopic,
                    'saved' => 'توضیحات دسته ذخیره شد.',
                    'missing' => 'دسته پیدا نشد؛ از منو دوباره انتخاب کنید.',
                ][$topicResult] ?? null;
                return $response === null ? null : $response->sendMessage($chatId, $response);
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

    if ($membershipFlow->eligible((int)$chatId, $topicId)) {
        $Topics = $catalogue->children((int)$topicId);
        if (count($Topics) < 1) {
            if ($membershipFlow->request((int)$chatId, $topicId)) {
                $TName = $membershipFlow->path($topicId);
                $TXT = "درخواست عضویت در دسته قدیمی: $TName";

                $keyboard = [[['text' => 'تایید ✅', 'callback_data' => "approve:$chatId:$topicId"]]];
                $keyboard = json_encode(['inline_keyboard' => $keyboard, 'resize_keyboard' => true], JSON_UNESCAPED_UNICODE);

                $response->bot('sendMessage', ['chat_id' => $owners[0], 'text' => $TXT, 'reply_markup' => $keyboard, '_subject_user' => (int)$chatId]);
                foreach ($owners as $admin) {
                    if ((int)$admin !== (int)$owners[0]) {
                        $response->bot('sendMessage', ['chat_id' => $admin, 'text' => $TXT, '_subject_user' => (int)$chatId]);
                    }
                }

                $response->sendMessage($chatId, "$reqJoin");
            }
        } else {
            foreach ($Topics as $Topic) {
                if ((is_array($perline) && ($perline[$m] ?? null) == $n) || $n == $perline) {
                    $m++;
                    $n = 0;
                }

                $TopicId = $Topic['id'];
                $subsettopics = $catalogue->children((int)$TopicId);
                $Name = $Topic['Name'];
                $caption .= "\n$Name : " . ($Topic['caption'] ?? '');

                if (count($subsettopics) < 1) {
                    $check = $membershipFlow->status((int)$chatId, $TopicId);
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
            $keyboard[$m][] = ['text' => "بازگشت", 'callback_data' => "BackToHome"];
            $keyboard = json_encode(['inline_keyboard' => $keyboard, 'resize_keyboard' => true], JSON_UNESCAPED_UNICODE);
            $response->sendkeyboard($chatId, "$caption", $keyboard);
        }
    }
}

if ($data === "BackHome") {
    if (!in_array((int)$fromId, $owners, true)) {
        return null;
    }

    $Topics = $catalogue->children(0);
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
    $keyboard[$m][] = ['text' => "ایجاد دسته", 'callback_data' => "AddTopic-0"];
    $keyboard[$m + 1][] = ['text' => "بازگشت", 'callback_data' => "BackHome"];
    $keyboard = json_encode(['inline_keyboard' => $keyboard, 'resize_keyboard' => true], JSON_UNESCAPED_UNICODE);
    return $response->editMessageKeyboard($chatId, $messageId, 'دسته‌ها', $keyboard, null);
}

if ($data === "BackToHome") {
    if ($membershipFlow->user((int)$chatId) !== null) {
        $checker = ($membershipFlow->user((int)$chatId)['profile'] ?? null);
        if ($checker == "true") {
            $perline = 2;
            $keyboard = array();
            $n = $m = 0;
            $Topics = $catalogue->children(0);

            foreach ($Topics as $button) {
                $id = $button['id'];
                $Name = $button['Name'];
                $check = $membershipFlow->status((int)$chatId, $id);

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
            return $response->editMessageKeyboard($chatId, $messageId, 'عضویت‌های قدیمی دسته‌ها', $keyboard, null);
        }

        $response->sendMessage($chatId, "$profileFalse");
    } else {
        $membershipFlow->ensureUser((int)$chatId);
        $response->sendMessage($chatId, "$start");
    }

} elseif (is_string($data) && (str_starts_with($data, 'approve:') || str_starts_with($data, 'taeed'))) {
    if (!in_array((int)$fromId, $owners, true)) {
        return null;
    }
    // Accept short new callbacks and existing legacy approval buttons.
    if (preg_match('/^approve:([1-9][0-9]*):([1-9][0-9]*)$/D', $data, $match)) {
        [, $chatid, $topicId] = $match;
    } elseif (preg_match('/^taeed;;([1-9][0-9]*);;topic([1-9][0-9]*);;[0-9|]+$/D', $data, $match)) {
        [, $chatid, $topicId] = $match;
    } else {
        return null;
    }
    $Names = $membershipFlow->approve((int)$fromId, $chatid, $topicId);
    if ($Names === null) {
        return null;
    }
    if (is_string($callbackId) && $callbackId !== '') {
        $response->bot('answerCallbackQuery', [
            'callback_query_id' => $callbackId,
            'text' => "عضویت تأیید شد.",
            'show_alert' => true,
        ]);
    }

    $response->editMessageText($chatId, $messageId, "تایید شده ✅\n\n$Names");
    $acceptTopic = "مدیریت ربات با عضویت شما در گروه $Names موافقت کرد✅";
    $response->sendMessage($chatid, "$acceptTopic");

} elseif (is_string($data) && preg_match('/^(delete|confirmDelete)-([1-9][0-9]*)$/D', $data, $match)) {
    if (!in_array((int)$fromId, $owners, true)) {
        return null;
    }
    $topicId = (int)$match[2];
    if ($match[1] === 'delete') {
        $topicForms->beginDelete((int)$fromId, (int)$chatId, $topicId);
        $keyboard = json_encode(['inline_keyboard' => [
            [['text' => 'تأیید حذف دسته و تمام زیرمجموعه‌ها', 'callback_data' => "confirmDelete-$topicId"]],
            [['text' => 'انصراف', 'callback_data' => 'BackHome']],
        ]], JSON_UNESCAPED_UNICODE);
        return $response->sendkeyboard($chatId, 'این کار دسته، تمام زیرمجموعه‌ها و درخواست‌های مرتبط را حذف می‌کند. تأیید می‌کنید؟', $keyboard);
    }
    $deleted = $topicForms->confirmDelete((int)$fromId, (int)$chatId, $topicId);
    if ($deleted === null) {
        return $response->sendMessage($chatId, 'ابتدا حذف دسته را از منو انتخاب و تأیید کنید.');
    }
    return $response->sendMessage($chatId, "تعداد دسته‌های حذف‌شده: $deleted");
}
