<?php

function migrateProduct(PDO $pdo): void
{
    if ((int)$pdo->query("SELECT GET_LOCK('travel_tour_product_migrations', 10)")->fetchColumn() !== 1) {
        throw new RuntimeException('Migration lock unavailable.');
    }
    try {
        $version = '002_product';
        $query = $pdo->prepare('SELECT version FROM SchemaMigrations WHERE version = ?');
        $query->execute([$version]);
        if ($query->fetchColumn() !== false) {
            return;
        }
        // Additive, resumable DDL. Never infer tours/capacities from legacy memberships.
        $sql = file_get_contents(__DIR__ . '/migrations/002_product.sql');
        foreach (explode(';', $sql) as $statement) {
            if (trim($statement) !== '') {
                $pdo->exec($statement);
            }
        }
        $pdo->prepare('INSERT INTO SchemaMigrations(version) VALUES (?)')->execute([$version]);
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('travel_tour_product_migrations')");
    }
}

/** Additive identity storage: legacy profile data is not copied into encrypted records. */
function migrateTravelerIdentity(PDO $pdo): void
{
    if ((int)$pdo->query("SELECT GET_LOCK('travel_tour_identity_migrations',10)")->fetchColumn() !== 1) {
        throw new RuntimeException('Identity migration lock unavailable.');
    }
    try {
        $version='003_traveler_identity';
        $check=$pdo->prepare('SELECT version FROM SchemaMigrations WHERE version=?');
        $check->execute([$version]);
        if ($check->fetchColumn() !== false) return;
        $pdo->exec(file_get_contents(__DIR__.'/migrations/003_traveler_identity.sql'));
        $pdo->prepare('INSERT INTO SchemaMigrations(version) VALUES (?)')->execute([$version]);
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('travel_tour_identity_migrations')");
    }
}

/** Persist Telegram cooldown across new arrivals, account erasure and worker restarts. */
function migrateNotificationDeliveryState(PDO $pdo): void
{
    if ((int)$pdo->query("SELECT GET_LOCK('travel_tour_delivery_migrations',10)")->fetchColumn() !== 1) {
        throw new RuntimeException('Notification delivery migration lock unavailable.');
    }
    try {
        $version = '004_notification_delivery_state';
        $check = $pdo->prepare('SELECT version FROM SchemaMigrations WHERE version=?');
        $check->execute([$version]);
        if ($check->fetchColumn() !== false) return;
        $pdo->exec(file_get_contents(__DIR__ . '/migrations/004_notification_delivery_state.sql'));
        $pdo->prepare('INSERT INTO SchemaMigrations(version) VALUES (?)')->execute([$version]);
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('travel_tour_delivery_migrations')");
    }
}
