<?php

/** Relational replacement for legacy Data.topic<id> membership columns. */
final class TopicMemberships
{
    public function __construct(private PDO $pdo)
    {
    }

    public function status(int|string $userId, int|string $topicId): ?string
    {
        $query = $this->pdo->prepare('SELECT status FROM TopicMemberships WHERE user_id = ? AND topic_id = ?');
        $query->execute([$userId, $topicId]);
        $status = $query->fetchColumn();
        return $status === false ? null : (string)$status;
    }

    /** Returns true only for the first request, even under concurrent delivery. */
    public function request(int|string $userId, int|string $topicId): bool
    {
        // INSERT IGNORE would also suppress foreign-key errors. Handle only duplicates.
        try {
            $query = $this->pdo->prepare("INSERT INTO TopicMemberships (user_id, topic_id, status) VALUES (?, ?, 'check')");
            $query->execute([$userId, $topicId]);
            return true;
        } catch (PDOException $error) {
            if (($error->errorInfo[1] ?? null) === 1062) {
                return false;
            }
            throw $error;
        }
    }

    /** Compare-and-set prevents repeated approval callbacks from notifying again. */
    public function approve(int|string $userId, int|string $topicId): bool
    {
        $query = $this->pdo->prepare("UPDATE TopicMemberships SET status = 'verify' WHERE user_id = ? AND topic_id = ? AND status = 'check'");
        $query->execute([$userId, $topicId]);
        return $query->rowCount() === 1;
    }

    public function approvedUsers(int|string $topicId): array
    {
        $query = $this->pdo->prepare("SELECT user_id FROM TopicMemberships WHERE topic_id = ? AND status = 'verify' ORDER BY user_id");
        $query->execute([$topicId]);
        return $query->fetchAll(PDO::FETCH_COLUMN);
    }
}
