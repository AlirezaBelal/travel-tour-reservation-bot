<?php

/**
 * Optional identity verification for tour operators that require Iranian mobile
 * numbers and national codes. Never put the raw values in logs, outbox or CSV.
 * PROFILE_ENCRYPTION_KEY is base64-encoded, 32-byte random key kept off Git.
 */
final class TravelerIdentity
{
    public function __construct(private PDO $pdo)
    {
    }

    public function status(int $actor): array
    {
        $row = Database::run(
            $this->pdo,
            'SELECT mobile_cipher IS NOT NULL AS mobile_ok, national_cipher IS NOT NULL AS national_ok FROM TravelerIdentity WHERE user_id=?',
            [$actor]
        )->fetch(PDO::FETCH_ASSOC);
        return ['mobile' => (bool)($row['mobile_ok'] ?? false), 'national' => (bool)($row['national_ok'] ?? false)];
    }

    public function complete(int $actor): bool
    {
        $status = $this->status($actor);
        return $status['mobile'] && $status['national'];
    }

    public function saveMobile(int $actor, mixed $contact): void
    {
        if (!isVerifiedSharedContact($contact, $actor)) {
            throw new RuleViolation('برای امنیت، فقط با دکمه «اشتراک شماره خودم» شماره متعلق به حساب خود را ارسال کنید.');
        }
        $phone = normalizeIranianMobile((string)$contact['phone_number']);
        $this->save($actor, 'mobile_cipher', $phone);
    }

    public function saveNational(int $actor, string $input): void
    {
        $code = strtr(trim($input), ['۰' => '0','۱' => '1','۲' => '2','۳' => '3','۴' => '4','۵' => '5','۶' => '6','۷' => '7','۸' => '8','۹' => '9']);
        if (!isValidIranianNationalCode($code)) {
            throw new RuleViolation('کد ملی باید ۱۰ رقم معتبر با رقم کنترل صحیح باشد. دوباره وارد کنید.');
        }
        $this->save($actor, 'national_cipher', $code);
    }

    private function save(int $actor, string $field, string $value): void
    {
        // The field is selected exclusively by our own two callers, never user input.
        $key = $this->key();
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = $nonce . sodium_crypto_secretbox($value, $nonce, $key);
        Database::run(
            $this->pdo,
            "INSERT INTO TravelerIdentity(user_id,$field) VALUES (?,?) ON DUPLICATE KEY UPDATE $field=VALUES($field),updated_at=UTC_TIMESTAMP()",
            [$actor,$cipher]
        );
        sodium_memzero($key);
    }

    private function key(): string
    {
        if (!function_exists('sodium_crypto_secretbox')) {
            throw new RuntimeException('Libsodium is required for encrypted profiles.');
        }
        $encoded = $_ENV['PROFILE_ENCRYPTION_KEY'] ?? getenv('PROFILE_ENCRYPTION_KEY');
        $key = is_string($encoded) ? base64_decode($encoded, true) : false;
        if (!is_string($key) || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new RuleViolation('ثبت اطلاعات هویتی موقتاً غیرفعال است؛ مدیر باید کلید رمزگذاری را در سرور تنظیم کند.');
        }
        return $key;
    }
}
