-- 0.6.8: the sticker packs, each a block of its own: their order and the sticker shown for them (the stickers
-- keep naming their pack; a pack not yet here is added when the stickers are next looked at)
CREATE TABLE IF NOT EXISTS `#__askdesk_sticker_packs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL DEFAULT '',
  `cover` int unsigned NOT NULL DEFAULT 0,
  `ordering` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
