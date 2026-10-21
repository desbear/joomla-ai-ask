-- 0.1.3: phrases the people answering often write
CREATE TABLE IF NOT EXISTS `#__askdesk_phrases` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL DEFAULT 0,
  `title` varchar(60) NOT NULL DEFAULT '',
  `text` text NOT NULL,
  `pinned` tinyint NOT NULL DEFAULT 0,
  `published` tinyint NOT NULL DEFAULT 1,
  `ordering` int NOT NULL DEFAULT 0,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`, `published`, `ordering`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
