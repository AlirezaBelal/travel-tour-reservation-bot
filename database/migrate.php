<?php

/** Additive migration: never drops or rewrites legacy profile columns. */
function migrateTopicMemberships(PDO $pdo): void
{
    $locked = $pdo->query("SELECT GET_LOCK('travel_tour_memberships_v1', 10)")->fetchColumn();
    if ((int)$locked !== 1) {
        throw new RuntimeException('Another migration is running.');
    }
    try {
        $pdo->exec('CREATE TABLE IF NOT EXISTS SchemaMigrations (version VARCHAR(100) PRIMARY KEY, applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
        $applied = $pdo->prepare('SELECT version FROM SchemaMigrations WHERE version = ?');
        $applied->execute(['001_topic_memberships']);
        if ($applied->fetchColumn() !== false) {
            return;
        }
        // DDL commits implicitly in MySQL. All data transfer follows in one transaction.
        $pdo->exec("CREATE TABLE IF NOT EXISTS TopicMemberships (
            user_id BIGINT NOT NULL,
            topic_id BIGINT NOT NULL,
            status VARCHAR(16) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, topic_id),
            INDEX idx_memberships_topic_status (topic_id, status),
            CONSTRAINT fk_memberships_user FOREIGN KEY (user_id) REFERENCES Data (UserId) ON DELETE CASCADE,
            CONSTRAINT fk_memberships_topic FOREIGN KEY (topic_id) REFERENCES Topics (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $columns = $pdo->query('SHOW COLUMNS FROM Data')->fetchAll(PDO::FETCH_COLUMN);
        $pdo->beginTransaction();
        foreach ($columns as $column) {
            if (!preg_match('/^topic([1-9][0-9]*)$/D', $column, $match)) {
                continue;
            }
            // Identifier comes only from the strict numeric column-name pattern above.
            $invalid = $pdo->query("SELECT COUNT(*) FROM Data WHERE `$column` IS NOT NULL AND `$column` NOT IN ('', 'check', 'verify')")->fetchColumn();
            if ((int)$invalid !== 0) {
                throw new RuntimeException('Unrecognized legacy status; migration rolled back. Inspect legacy data privately.');
            }
            // Orphan references fail the FK instead of silently discarding requests.
            $copy = $pdo->prepare("INSERT INTO TopicMemberships (user_id, topic_id, status) SELECT UserId, ?, `$column` FROM Data WHERE `$column` IN ('check', 'verify')");
            $copy->execute([$match[1]]);
        }
        $pdo->prepare('INSERT INTO SchemaMigrations (version) VALUES (?)')->execute(['001_topic_memberships']);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('travel_tour_memberships_v1')");
    }
}
