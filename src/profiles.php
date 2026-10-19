<?php

function isAdmin(int|string|null $chatId): bool
{
    if ($chatId === null || $chatId === false || $chatId === '') {
        return false;
    }

    return in_array((int)$chatId, ADMINS, true);
}


function hasdb(int|string $userid): bool
{
    global $db;

    if (!$db->has('Data', ['UserId' => (string)$userid])) {
        $db->insert('Data', [
            'UserId' => (string)$userid,
            'step' => 'defult',
        ]);
        return false;
    }

    return true;
}
