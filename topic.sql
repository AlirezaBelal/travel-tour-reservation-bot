CREATE DATABASE IF NOT EXISTS travel_tour_bot
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE travel_tour_bot;

-- Learner/traveler profile and workflow state.
-- Profile fields stay nullable until the multi-step onboarding flow is complete.
CREATE TABLE IF NOT EXISTS `Data` (
    `UserId` BIGINT NOT NULL,
    `Name` VARCHAR(150) DEFAULT NULL,
    `Mobile` VARCHAR(24) DEFAULT NULL,
    `N_Code` VARCHAR(20) DEFAULT NULL,
    `birthday` VARCHAR(10) DEFAULT NULL,
    `sex` VARCHAR(20) DEFAULT NULL,
    `profile` VARCHAR(16) DEFAULT NULL,
    `step` VARCHAR(64) NOT NULL DEFAULT 'defult',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`UserId`),
    INDEX `idx_data_profile` (`profile`),
    INDEX `idx_data_step` (`step`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Hierarchical tour/group catalogue. Groups=0 denotes a root item;
-- otherwise Groups contains the parent topic id.
CREATE TABLE IF NOT EXISTS `Topics` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `Name` VARCHAR(300) NOT NULL,
    `Groups` BIGINT NOT NULL DEFAULT 0,
    `caption` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_topics_parent` (`Groups`),
    INDEX `idx_topics_name` (`Name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The legacy application stores per-topic approval state as dynamically added
-- topic<id> columns on Data. That model is preserved for compatibility with the
-- current runtime. A future migration should normalize those values into a
-- user_topic_status table before removing the dynamic columns.
