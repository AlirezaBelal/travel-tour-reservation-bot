-- Optimized SQL Schema for Topic Management

-- Table: Data
CREATE TABLE `Data`
(
    `UserId`   BIGINT(20)                       NOT NULL COMMENT 'Unique identifier for the user (e.g., Telegram user ID)',
    `Name`     VARCHAR(100)                     NOT NULL COMMENT 'Full name of the user',
    `Mobile`   VARCHAR(20)                      NOT NULL COMMENT 'User mobile number',
    `N_Code`   VARCHAR(20)                      NOT NULL COMMENT 'National ID or identifier',
    `birthday` DATE                             NOT NULL COMMENT 'Date of birth',
    `sex`      ENUM ('Male', 'Female', 'Other') NOT NULL COMMENT 'Gender of the user',
    `profile`  VARCHAR(255) DEFAULT NULL COMMENT 'Profile picture URL or path',
    `step`     VARCHAR(50)                      NOT NULL COMMENT 'Current step in the user workflow',
    PRIMARY KEY (`UserId`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- Table: Topics
CREATE TABLE `Topics`
(
    `id`      BIGINT(20)   NOT NULL AUTO_INCREMENT COMMENT 'Unique topic identifier',
    `Name`    VARCHAR(300) NOT NULL COMMENT 'Topic name',
    `Groups`  TEXT         NOT NULL COMMENT 'Groups related to the topic',
    `caption` TEXT         NOT NULL COMMENT 'Detailed description or caption of the topic',
    PRIMARY KEY (`id`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- Index for faster lookups
CREATE INDEX idx_name ON `Topics` (`Name`);
