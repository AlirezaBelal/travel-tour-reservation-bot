<?php

declare(strict_types=1);

/** Pure rendering shared by the historical command and back-button routes.
 * @phpstan-type Topic array{id: int|string, Name: string, Groups?: int|string, caption?: string|null}
 * @phpstan-type Rows list<list<array{text: string, callback_data: string}>>
 */
final class LegacyKeyboard
{
    /** @param list<Topic> $topics @return Rows */
    public static function administration(array $topics, int|string $parent): array
    {
        $rows = [];
        foreach ($topics as $topic) {
            $id = $topic['id'];
            $rows[] = [
                ['text' => $topic['Name'], 'callback_data' => "sub-$id"],
                ['text' => '🗑', 'callback_data' => "delete-$id"],
            ];
        }
        $rows[] = [['text' => 'ایجاد دسته', 'callback_data' => "AddTopic-$parent"]];
        $rows[] = [['text' => 'بازگشت', 'callback_data' => 'BackHome']];
        return $rows;
    }

    /** @param list<Topic> $topics @param Closure(int|string): string $icon @return Rows */
    public static function membership(array $topics, Closure $icon): array
    {
        $rows = [];
        foreach ($topics as $topic) {
            $id = $topic['id'];
            $rows[] = [
                ['text' => $topic['Name'], 'callback_data' => "join-$id"],
                ['text' => $icon($id), 'callback_data' => "join-$id"],
            ];
        }
        return $rows;
    }

    /** @param list<Topic> $topics @return Rows */
    public static function broadcast(array $topics): array
    {
        $rows = [];
        foreach ($topics as $topic) {
            $rows[] = [['text' => $topic['Name'], 'callback_data' => 'send' . $topic['id']]];
        }
        $rows[] = [['text' => 'ارسال به همه ', 'callback_data' => 'sendAll']];
        return $rows;
    }

    public static function statusIcon(?string $status): string
    {
        return match ($status) {
            'verify' => '✅',
            'check' => '🔄',
            default => '❌',
        };
    }

    /** @param Rows $rows */
    public static function encode(array $rows): string
    {
        return json_encode(['inline_keyboard' => $rows, 'resize_keyboard' => true], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
