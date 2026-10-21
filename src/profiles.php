<?php

function isAdmin(int|string|null $chatId): bool
{
    if ($chatId === null || $chatId === '') {
        return false;
    }

    return in_array((int)$chatId, ADMINS, true);
}
