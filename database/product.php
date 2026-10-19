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
