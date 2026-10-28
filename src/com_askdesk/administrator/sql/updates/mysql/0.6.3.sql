-- 0.6.3: the e-mails the desk sends, as designed in the administrator, one for each language
CREATE TABLE IF NOT EXISTS `#__askdesk_mails` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `kind` varchar(20) NOT NULL,
  `language` varchar(10) NOT NULL,
  `subject` varchar(255) NOT NULL DEFAULT '',
  `html` mediumtext NOT NULL,
  `design` mediumtext NOT NULL,
  `published` tinyint NOT NULL DEFAULT 1,
  `modified` datetime NOT NULL,
  `modified_by` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_kind_language` (`kind`, `language`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
