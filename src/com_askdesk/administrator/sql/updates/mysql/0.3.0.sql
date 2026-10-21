-- 0.3.0: browsers to notify (Web Push), and the site's keys for it
CREATE TABLE IF NOT EXISTS `#__askdesk_push` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL DEFAULT 0,
  `endpoint` text NOT NULL,
  `endpoint_hash` char(64) NOT NULL,
  `p256dh` varchar(100) NOT NULL DEFAULT '',
  `auth` varchar(40) NOT NULL DEFAULT '',
  `created` datetime NOT NULL,
  `last_sent` datetime NULL DEFAULT NULL,
  `failures` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_endpoint` (`endpoint_hash`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__askdesk_secrets` (
  `name` varchar(50) NOT NULL,
  `value` text NOT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
