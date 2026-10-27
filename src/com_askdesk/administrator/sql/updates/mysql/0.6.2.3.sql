-- 0.6.2.3: a message left marked as a ticket (seen by its visitor after it is closed too)
ALTER TABLE `#__askdesk_rooms` ADD COLUMN `ticket` tinyint NOT NULL DEFAULT 0;
UPDATE `#__askdesk_rooms` SET `ticket` = 1 WHERE `status` = 'message';
-- and one-time codes a visitor asks for to see their tickets (the address and the code hashed; kept minutes)
CREATE TABLE IF NOT EXISTS `#__askdesk_codes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `email` char(64) NOT NULL,
  `code` char(64) NOT NULL,
  `ip` char(64) NOT NULL DEFAULT '',
  `tries` tinyint unsigned NOT NULL DEFAULT 0,
  `created` datetime NOT NULL,
  `expires` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `idx_created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
