-- 0.6.2.1: records changed from the dashboard, told to the others looking at them (which, not what; kept an hour)
CREATE TABLE IF NOT EXISTS `#__askdesk_source_changes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `source` varchar(30) NOT NULL,
  `template` varchar(100) NOT NULL,
  `record` varchar(100) NOT NULL,
  `user_id` int unsigned NOT NULL DEFAULT 0,
  `deleted` tinyint NOT NULL DEFAULT 0,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
