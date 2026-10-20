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

        $id = (int)$candidate;
        if ($id > 0 && $id <= 4503599627370495) $ids[] = $id;
    }

    return array_values(array_unique($ids));
}

function isSensitiveAdminCallback(mixed $data): bool
{
    if (!is_string($data) || $data === '') {
        return false;
    }

    foreach (['send', 'sub-', 'AddTopic-', 'delete-', 'confirmDelete-', 'approve:', 'taeed'] as $prefix) {
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

/** Accept only an owner's Telegram request_contact submission, never a forwarded third-party card. */
function isVerifiedSharedContact(mixed $contact, int $actor): bool
{
    return is_array($contact)
        && is_int($contact['user_id'] ?? null)
        && $contact['user_id'] === $actor
        && is_string($contact['phone_number'] ?? null)
        && strlen($contact['phone_number']) <= 24
        && isIranianMobileNumber($contact['phone_number']);
}

function normalizeIranianMobile(string $phone): string
{
    $digits = preg_replace('/[^0-9]/', '', $phone);
    if (!is_string($digits) || !isIranianMobileNumber($phone)) {
        throw new InvalidArgumentException('Invalid Iranian mobile number.');
    }
    return '+98' . substr($digits, -10);
}

/** Old Data.step values are routing markers, never authorization to collect PII. */
function isLegacyProfileStep(mixed $step): bool
{
    return is_string($step) && in_array($step, ['name', 'contact', 'NationalCode', 'sex', 'birthday'], true);
}
