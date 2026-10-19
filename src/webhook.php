<?php

/** Keep authentication independent of the database and Telegram client. */
function webhookSecretIsValid(string $expected, mixed $provided): bool
{
    return preg_match('/^[A-Za-z0-9_-]{32,256}$/D', $expected) === 1
        && is_string($provided)
        && hash_equals($expected, $provided);
}

function isTelegramId(mixed $value): bool
{
    // Telegram IDs have at most 52 significant bits; reject coercible values.
    return is_int($value) && $value > 0 && $value <= 4503599627370495;
}

/** Only private, actor-owned conversations may read or mutate profiles. */
function isSupportedPrivateUpdate(mixed $update): bool
{
    if (!is_array($update) || !isset($update['update_id'])
        || !is_int($update['update_id']) || $update['update_id'] < 0) {
        return false;
    }

    // Edited messages must not replay a state-changing conversation step.
    if (isset($update['edited_message'])) {
        return false;
    }

    if (isset($update['message']) && !isset($update['callback_query'])) {
        $message = $update['message'];
        $actor = is_array($message) ? ($message['from'] ?? null) : null;
    } elseif (isset($update['callback_query']) && !isset($update['message'])) {
        $callback = $update['callback_query'];
        if (!is_array($callback) || !is_string($callback['id'] ?? null)
            || $callback['id'] === '' || !is_string($callback['data'] ?? null)
            || strlen($callback['data']) < 1 || strlen($callback['data']) > 64) {
            return false;
        }
        $message = $callback['message'] ?? null;
        $actor = $callback['from'] ?? null;
    } else {
        return false;
    }

    if (!is_array($message) || !is_array($actor)
        || !isTelegramId($actor['id'] ?? null)
        || ($actor['is_bot'] ?? false) !== false
        || !is_int($message['message_id'] ?? null) || $message['message_id'] <= 0) {
        return false;
    }
    $chat = $message['chat'] ?? null;
    return is_array($chat) && ($chat['type'] ?? null) === 'private'
        && isTelegramId($chat['id'] ?? null) && $chat['id'] === $actor['id'];
}
