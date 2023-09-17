-- Table structure for table `users`

CREATE TABLE `users` (
                         `id` int(11) NOT NULL,
                         `first_name` varchar(100) DEFAULT NULL,
                         `last_name` varchar(100) DEFAULT NULL,
                         `username` varchar(100) DEFAULT NULL,
                         `user_id` int(20) NOT NULL,
                         `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

-- Indexes for table `users`
ALTER TABLE `users`
    ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);