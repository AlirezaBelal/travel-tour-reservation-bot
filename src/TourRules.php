<?php

declare(strict_types=1);

/** Tour value validation without persistence, delivery or configuration. */
final class TourRules
{
    public const FIELDS = ['category_id', 'title', 'destination', 'description', 'starts_at', 'ends_at', 'price', 'capacity', 'publication'];

    /**
     * @param array<string, mixed> $values
     */
    public static function validate(array $values): void
    {
        $fields = self::FIELDS;
        foreach ($fields as $field) {
            if (!array_key_exists($field, $values)) {
                throw new RuleViolation('مشخصات تور کامل نیست.');
            }
        }
        foreach (['title','destination','description'] as $field) {
            if (!is_string($values[$field]) || trim($values[$field]) === '' || strlen($values[$field]) > ($field === 'description' ? 4000 : 600)) {
                throw new RuleViolation('عنوان، مقصد و توضیحات تور را به‌درستی وارد کنید.');
            }
        }
        foreach (['starts_at','ends_at'] as $field) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', (string)$values[$field], new DateTimeZone('UTC'));
            if (!$date || $date->format('Y-m-d H:i:s') !== $values[$field]) {
                throw new RuleViolation('تاریخ یا ساعت معتبر نیست.');
            }
        }
        if ($values['ends_at'] <= $values['starts_at']) {
            throw new RuleViolation('زمان پایان تور باید بعد از زمان شروع باشد.');
        }
        if (
            !is_int($values['price']) || $values['price'] < 0 || $values['price'] > 1000000000000
            || !is_int($values['capacity']) || $values['capacity'] < 1 || $values['capacity'] > 100000
            || !in_array($values['publication'], ['draft','published','inactive','archived'], true)
        ) {
            throw new RuleViolation('قیمت، ظرفیت، تاریخ یا وضعیت تور معتبر نیست.');
        }
    }
}
