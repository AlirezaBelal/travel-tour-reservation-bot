<?php

/**
 * Parse a comma-separated administrator ID list into unique Telegram IDs.
 *
 * @return array<int, int>
 */
function parseAdminIds(string $value): array
{
    $ids = [];

    foreach (explode(',', $value) as $candidate) {
        $candidate = trim($candidate);
        if ($candidate === '' || !preg_match('/^\d+$/', $candidate)) {
            continue;
        }

        $ids[] = (int)$candidate;
    }

    return array_values(array_unique($ids));
}

function isSensitiveAdminCallback(mixed $data): bool
{
    if (!is_string($data) || $data === '') {
        return false;
    }

    foreach (['send', 'sub-', 'AddTopic-', 'delete-', 'taeed'] as $prefix) {
        if (str_starts_with($data, $prefix)) {
            return true;
        }
    }

    return $data === 'BackHome';
}

function isValidIranianNationalCode(string $input): bool
{
    if (!preg_match('/^\d{10}$/', $input)) {
        return false;
    }

    if (preg_match('/^(\d)\1{9}$/', $input)) {
        return false;
    }

    $check = (int)$input[9];
    $sum = 0;

    for ($index = 0; $index < 9; $index++) {
        $sum += ((int)$input[$index]) * (10 - $index);
    }

    $remainder = $sum % 11;
    $expected = $remainder < 2 ? $remainder : 11 - $remainder;

    return $check === $expected;
}

function isIranianMobileNumber(string $phoneNumber): bool
{
    $normalized = preg_replace('/[^0-9+]/', '', trim($phoneNumber));
    if (!is_string($normalized) || $normalized === '') {
        return false;
    }

    return preg_match('/^(?:\+98|0098|98|0)?9\d{9}$/', $normalized) === 1;
}
