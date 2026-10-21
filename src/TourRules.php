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
            self::validateText($field, $values[$field]);
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

    /** Shared by the form and application boundary; VARCHAR limits count Unicode characters. */
    public static function validateText(string $field, mixed $value): void
    {
        $valid = is_string($value) && trim($value) !== '' && (
            $field === 'description'
                ? strlen($value) <= 4000 && preg_match('//u', $value) === 1
                : in_array($field, ['title', 'destination'], true) && preg_match('/\A.{1,200}\z/us', $value) === 1
        );
        if (!$valid) {
            throw new RuleViolation('عنوان و مقصد باید ۱ تا ۲۰۰ نویسه باشند؛ توضیحات را کوتاه و غیرخالی وارد کنید.');
        }
    }
}
