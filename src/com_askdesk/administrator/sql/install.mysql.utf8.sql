CREATE TABLE IF NOT EXISTS `#__askdesk_rooms` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `token` char(64) NOT NULL,
  `agent_id` int unsigned NOT NULL DEFAULT 0,
  `status` varchar(10) NOT NULL DEFAULT 'waiting',
  `staff_id` int unsigned NOT NULL DEFAULT 0,
  `user_id` int unsigned NOT NULL DEFAULT 0,
  `name` varchar(100) NOT NULL DEFAULT '',
  `email` varchar(255) NOT NULL DEFAULT '',
  `company` varchar(150) NOT NULL DEFAULT '',
  `page_language` varchar(10) NOT NULL DEFAULT '',
  `visitor_language` varchar(10) NOT NULL DEFAULT '',
  `staff_language` varchar(10) NOT NULL DEFAULT '',
  `page` varchar(500) NOT NULL DEFAULT '',
  `pages` mediumtext NULL DEFAULT NULL,
  `entry` mediumtext NOT NULL,
  `last_message` int unsigned NOT NULL DEFAULT 0,
  `staff_seen` int unsigned NOT NULL DEFAULT 0,
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  `visitor_seen` datetime NULL DEFAULT NULL,
  `visitor_typing` datetime NULL DEFAULT NULL,
  `staff_typing` datetime NULL DEFAULT NULL,
  `closed` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_token` (`token`),
  KEY `idx_status` (`status`, `modified`),
  KEY `idx_user` (`user_id`),
  KEY `idx_staff` (`staff_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__askdesk_messages` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `room_id` int unsigned NOT NULL,
  `author` varchar(10) NOT NULL,
  `staff_id` int unsigned NOT NULL DEFAULT 0,
  `text` mediumtext NOT NULL,
  `language` varchar(10) NOT NULL DEFAULT '',
  `translation` mediumtext NOT NULL,
  `translation_language` varchar(10) NOT NULL DEFAULT '',
  `suggestions` mediumtext NULL DEFAULT NULL,
  `links` mediumtext NULL DEFAULT NULL,
  `file_id` int unsigned NOT NULL DEFAULT 0,
  `sticker_id` int unsigned NOT NULL DEFAULT 0,
  `translating` tinyint NOT NULL DEFAULT 0,
  `draft` mediumtext NULL DEFAULT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_room` (`room_id`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__askdesk_staff` (
  `user_id` int unsigned NOT NULL,
  `active` tinyint NOT NULL DEFAULT 0,
  `status` varchar(10) NOT NULL DEFAULT 'away',
  `language` varchar(10) NOT NULL DEFAULT '',
  `seen` datetime NULL DEFAULT NULL,
  `display_name` varchar(100) NOT NULL DEFAULT '',
  `avatar` varchar(100) NOT NULL DEFAULT '',
  `avatar_image` varchar(500) NOT NULL DEFAULT '',
  `thanks_image` varchar(500) NOT NULL DEFAULT '',
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__askdesk_files` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `room_id` int unsigned NOT NULL,
  `token` char(32) NOT NULL,
  `name` varchar(160) NOT NULL DEFAULT '',
  `kind` varchar(10) NOT NULL DEFAULT 'document',
  `size` int unsigned NOT NULL DEFAULT 0,
  `saved_as` varchar(64) NOT NULL DEFAULT '',
  `author` varchar(10) NOT NULL DEFAULT 'visitor',
  `staff_id` int unsigned NOT NULL DEFAULT 0,
  `created` datetime NOT NULL,
  `expires` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_token` (`token`),
  KEY `idx_room` (`room_id`),
  KEY `idx_expires` (`expires`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__askdesk_handled` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `room_id` int unsigned NOT NULL,
  `staff_id` int unsigned NOT NULL,
  `kind` varchar(10) NOT NULL DEFAULT 'chat',
  `member` tinyint NOT NULL DEFAULT 0,
  `waited` int unsigned NOT NULL DEFAULT 0,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_room` (`room_id`),
  KEY `idx_staff` (`staff_id`, `created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__askdesk_ratings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `room_id` int unsigned NOT NULL,
  `staff_id` int unsigned NOT NULL DEFAULT 0,
  `agent_id` int unsigned NOT NULL DEFAULT 0,
  `member` tinyint NOT NULL DEFAULT 0,
  `language` varchar(10) NOT NULL DEFAULT '',
  `ratings` text NOT NULL,
  `comment` text NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_room` (`room_id`),
  KEY `idx_created` (`created`),
  KEY `idx_staff` (`staff_id`, `created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__askdesk_stickers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `pack` varchar(100) NOT NULL DEFAULT '',
  `title` varchar(100) NOT NULL DEFAULT '',
  `image` varchar(255) NOT NULL DEFAULT '',
  `who` varchar(10) NOT NULL DEFAULT 'all',
  `published` tinyint NOT NULL DEFAULT 1,
  `ordering` int NOT NULL DEFAULT 0,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pack` (`published`, `pack`, `ordering`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

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
