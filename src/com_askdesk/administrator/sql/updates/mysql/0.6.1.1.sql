-- 0.6.1.1 (for a site already at 0.6.1): work cards in a chat (a form filled where only the people answering see it)
CREATE TABLE IF NOT EXISTS `#__askdesk_work` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `room_id` int unsigned NOT NULL,
  `staff_id` int unsigned NOT NULL DEFAULT 0,
  `panel` varchar(30) NOT NULL,
  `form` varchar(40) NOT NULL,
  `card` mediumtext NOT NULL,
  `state` varchar(10) NOT NULL DEFAULT 'open',
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_room` (`room_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
