-- Table structure for table `users`
CREATE TABLE `users` (
                         `id` INT(11) NOT NULL AUTO_INCREMENT,
                         `first_name` VARCHAR(100) DEFAULT NULL,
                         `last_name` VARCHAR(100) DEFAULT NULL,
                         `username` VARCHAR(100) DEFAULT NULL,
                         `user_id` INT(20) NOT NULL,
                         `date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                         PRIMARY KEY (`id`),
                         UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Explanation:
-- `id`: A unique identifier for each user. It auto-increments.
-- `first_name`: Stores the user's first name (up to 100 characters).
-- `last_name`: Stores the user's last name (up to 100 characters).
-- `username`: Stores the user's username (up to 100 characters).
-- `user_id`: A unique identifier for the user (not null).
-- `date`: A timestamp that tracks when the record was created and updated.

-- Indexes:
-- - `PRIMARY KEY`: Ensures the `id` field is unique and serves as the primary key.
-- - `UNIQUE KEY user_id`: Ensures that `user_id` is unique, preventing duplicate user entries.

-- Engine:
-- - `ENGINE=InnoDB`: Specifies the storage engine as InnoDB, which is a more robust engine than MyISAM.
-- - `DEFAULT CHARSET=utf8mb4`: Sets the character set to UTF-8mb4 for proper Unicode support.
