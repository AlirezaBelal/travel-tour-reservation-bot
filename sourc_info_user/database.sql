SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

CREATE TABLE IF NOT EXISTS `users` (
    `id` int(11) NOT NULL,
    `userID` varchar(16) COLLATE utf8_unicode_ci NOT NULL,
    `name` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
    `mobile` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
    `level` varchar(4) COLLATE utf8_unicode_ci NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


ALTER TABLE `users`
    ADD PRIMARY KEY (`id`);

ALTER TABLE `users`
    MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;