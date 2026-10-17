CREATE TABLE IF NOT EXISTS `#__mcpask_urls` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `url` varchar(1024) NOT NULL,
  `url_hash` char(40) NOT NULL,
  `selector` varchar(200) NOT NULL DEFAULT '',
  `created` datetime NOT NULL,
  `created_by` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_url` (`url_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__mcpask_ignores` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `source` varchar(32) NOT NULL,
  `item_id` int unsigned NOT NULL,
  `title` varchar(255) NOT NULL DEFAULT '',
  `url` varchar(1024) NOT NULL DEFAULT '',
  `note` varchar(255) NOT NULL DEFAULT '',
  `created` datetime NOT NULL,
  `created_by` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_item` (`source`, `item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
