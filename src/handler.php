<?php

/**
 * Scoped compatibility entry: variables are supplied only by LegacyHandlerAdapter.
 * @var TopicCatalogue $catalogue
 * @var LegacyTopicService $topicForms
 * @var LegacyMembershipService $membershipFlow
 * @var LegacyBroadcastService $broadcasts
 * @var LegacyResponder $responder
 * @var list<int> $owners
 * @var int $fromId
 * @var int $chatId
 * @var string|null $text
 * @var string|null $data
 * @var int|string $messageId
 * @var string|null $callbackId
 * @var bool $hasContent
 * @var string $start
 * @var string $list
 * @var string $profileFalse
 * @var string $selectSend
 * @var string $reqForward
 * @var string $forwardTrue
 * @var string $reqJoin
 * @var string $getTopic
 * @var string $captionTopic
 */
// Retired profile forms must never receive new sensitive values, even when this
// compatibility handler is invoked directly instead of through ProductBot.
if ((is_string($text) && preg_match('/^\/setprofile(?:@[A-Za-z0-9_]+)?(?:\s.*)?$/u', $text))
    || isLegacyProfileStep(($membershipFlow->user((int)$chatId)['step'] ?? null))) {
    $topicForms->reset((int)$chatId);
    return $responder->bot('sendMessage', [
        'chat_id' => $chatId,
        'text' => 'فرم قدیمی پروفایل بسته شد؛ اطلاعات این پیام ذخیره نشد. برای ثبت امن اطلاعات، /profile را بفرستید.',
        'reply_markup' => json_encode(['remove_keyboard' => true]),
    ]);
}

if (in_array((int)$fromId, $owners, true)) {

    if (is_string($text) && preg_match("/^[\/\#\!]?(AddTopic) (.*)$/i", $text)) {
        preg_match("/^[\/\#\!]?(AddTopic) (.*)$/i", $text, $match);
        $query = $match[2];

        $topicForms->createRoot((int)$fromId, (int)$chatId, $query);
        $T = 'دسته ایجاد شد.';

        return $responder->sendMessage($chatId, "$T");

    } elseif ($text === 'Topics') {
        $topics = array_filter($catalogue->all(), static fn (array $row): bool => (int)$row['Groups'] === 0);
        $keyboard = LegacyKeyboard::administration(array_values($topics), 0);
        return $responder->sendkeyboard($chatId, $list, LegacyKeyboard::encode($keyboard));

    } elseif ($text === '/send') {
        $keyboard = LegacyKeyboard::broadcast($catalogue->all());
        $responder->sendkeyboard($chatId, $selectSend, LegacyKeyboard::encode($keyboard));

    } elseif ($text === 'ping') {
        $responder->sendMessage($chatId, "Bot is reachable.");

    } elseif ($text === 'usage') {
        $memory = memory_get_usage(true) / 1024 / 1024;
        $responder->sendMessage($chatId, "Memory usage is " . round($memory, 2) . " MB");

    } elseif (is_string($data) && str_starts_with($data, 'send')) {
        $broadcasts->begin((int)$fromId, (int)$chatId, $data);
        return $responder->sendMessage($chatId, "$reqForward");

    } elseif (is_string($data) && str_starts_with($data, 'sub-')) {
        $id = substr($data, strlen('sub-'));
        if (!ctype_digit($id)) {
            return null;
        }
        $topics = $catalogue->children((int)$id);
        $caption = $topics === [] ? '...' : '';
        foreach ($topics as $topic) {
            $caption .= $topic['Name'] . ' : ' . ($topic['caption'] ?? '') . "\n\n";
        }
        $keyboard = LegacyKeyboard::administration($topics, $id);
        return $responder->editMessageKeyboard($chatId, $messageId, $caption, LegacyKeyboard::encode($keyboard), null);

    } elseif (is_string($data) && str_starts_with($data, 'AddTopic-')) {
        $parentId = substr($data, strlen('AddTopic-'));
        if (!ctype_digit((string)$parentId)) {
            return null;
        }
        $topicForms->begin((int)$fromId, (int)$chatId, (int)$parentId);
        return $responder->sendMessage($chatId, "$getTopic");
    }
}

if ($hasContent) {

    if (is_string($text) && preg_match('/^\/start(?:@[A-Za-z0-9_]+)?(?: [A-Za-z0-9_-]{1,64})?$/D', $text)) {
        $membershipFlow->ensureUser((int)$chatId);
        return $responder->sendMessage($chatId, "$start");

    } elseif ($text === '/list') {
        LegacyHandlerAdapter::membershipMenu($catalogue, $membershipFlow, $responder, $chatId, null, $list, $start, $profileFalse);

    } else {
        if ($membershipFlow->user((int)$chatId) !== null) {
            $broadcast = $broadcasts->consume((int)$fromId, (int)$chatId);
            if ($broadcast !== null) {
                if (!$broadcast['authorized']) {
                    return null;
                }
                foreach ($broadcast['recipients'] as $recipient) {
                    $responder->copymessage($recipient, $chatId, $messageId);
                }
                return $responder->sendMessage($chatId, "$forwardTrue");
            }

            $topicResult = $topicForms->submit((int)$fromId, (int)$chatId, is_string($text) ? $text : null);
            if ($topicResult !== null) {
                $response = [
                    'caption' => $captionTopic,
                    'saved' => 'توضیحات دسته ذخیره شد.',
                    'missing' => 'دسته پیدا نشد؛ از منو دوباره انتخاب کنید.',
                ][$topicResult] ?? null;
                return $response === null ? null : $responder->sendMessage($chatId, $response);
            }


        }
    }
}

if (is_string($data) && str_starts_with($data, 'join-')) {
    $caption = '';
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

                $responder->bot('sendMessage', ['chat_id' => $owners[0], 'text' => $TXT, 'reply_markup' => $keyboard, '_subject_user' => (int)$chatId]);
                foreach ($owners as $admin) {
                    if ((int)$admin !== (int)$owners[0]) {
                        $responder->bot('sendMessage', ['chat_id' => $admin, 'text' => $TXT, '_subject_user' => (int)$chatId]);
                    }
                }

                $responder->sendMessage($chatId, "$reqJoin");
            }
        } else {
            $icons = [];
            foreach ($Topics as $topic) {
                $id = $topic['id'];
                $caption .= "\n" . $topic['Name'] . ' : ' . ($topic['caption'] ?? '');
                $icons[$id] = $catalogue->children((int)$id) === []
                    ? LegacyKeyboard::statusIcon($membershipFlow->status($chatId, $id)) : '↗️';
            }
            $keyboard = LegacyKeyboard::membership($Topics, static fn (int|string $id): string => $icons[$id]);
            $keyboard[] = [['text' => 'بازگشت', 'callback_data' => 'BackToHome']];
            $responder->sendkeyboard($chatId, $caption, LegacyKeyboard::encode($keyboard));
        }
    }
}

if ($data === "BackHome") {
    if (!in_array((int)$fromId, $owners, true)) {
        return null;
    }

    $keyboard = LegacyKeyboard::administration($catalogue->children(0), 0);
    return $responder->editMessageKeyboard($chatId, $messageId, 'دسته‌ها', LegacyKeyboard::encode($keyboard), null);
}

if ($data === "BackToHome") {
    LegacyHandlerAdapter::membershipMenu($catalogue, $membershipFlow, $responder, $chatId, $messageId, $list, $start, $profileFalse);

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
        $responder->bot('answerCallbackQuery', [
            'callback_query_id' => $callbackId,
            'text' => "عضویت تأیید شد.",
            'show_alert' => true,
        ]);
    }

    $responder->editMessageText($chatId, $messageId, "تایید شده ✅\n\n$Names");
    $acceptTopic = "مدیریت ربات با عضویت شما در گروه $Names موافقت کرد✅";
    $responder->sendMessage($chatid, "$acceptTopic");

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
        return $responder->sendkeyboard($chatId, 'این کار دسته، تمام زیرمجموعه‌ها و درخواست‌های مرتبط را حذف می‌کند. تأیید می‌کنید؟', $keyboard);
    }
    $deleted = $topicForms->confirmDelete((int)$fromId, (int)$chatId, $topicId);
    if ($deleted === null) {
        return $responder->sendMessage($chatId, 'ابتدا حذف دسته را از منو انتخاب و تأیید کنید.');
    }
    return $responder->sendMessage($chatId, "تعداد دسته‌های حذف‌شده: $deleted");
}
