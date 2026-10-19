CREATE TABLE IF NOT EXISTS `#__mcpask_texts` (
  `source` varchar(32) NOT NULL,
  `item_id` int unsigned NOT NULL,
  `text` mediumtext NOT NULL,
  `mode` varchar(8) NOT NULL DEFAULT 'add',
  `modified` datetime NOT NULL,
  `modified_by` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`source`, `item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `#__mcpask_pets` ADD COLUMN `ordering` int NOT NULL DEFAULT 0 AFTER `params`;
