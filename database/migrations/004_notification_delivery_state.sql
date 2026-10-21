-- One bot-wide Telegram retry-after deadline, independent of queued recipients.
CREATE TABLE IF NOT EXISTS NotificationDeliveryState (
 id TINYINT NOT NULL PRIMARY KEY,
 blocked_until DATETIME NOT NULL,
 CHECK(id = 1)
) ENGINE=InnoDB;
