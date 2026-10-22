-- 0.5.0: each person's dashboard
CREATE TABLE IF NOT EXISTS `#__askdesk_boards` (
  `user_id` int unsigned NOT NULL,
  `layout` mediumtext NOT NULL,
  `updated` datetime NOT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
