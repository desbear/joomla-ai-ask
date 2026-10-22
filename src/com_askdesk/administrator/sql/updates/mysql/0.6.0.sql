-- 0.6.0: who wrote last in each chat; a chat closed (結案) by someone, or left to be; the e-mails about it
ALTER TABLE `#__askdesk_rooms` ADD COLUMN `last_author` varchar(10) NOT NULL DEFAULT '' AFTER `last_message`;
ALTER TABLE `#__askdesk_rooms` ADD COLUMN `settled` datetime NULL DEFAULT NULL AFTER `closed`;
ALTER TABLE `#__askdesk_rooms` ADD COLUMN `settled_by` int unsigned NOT NULL DEFAULT 0 AFTER `settled`;
ALTER TABLE `#__askdesk_rooms` ADD COLUMN `alerted` datetime NULL DEFAULT NULL AFTER `settled_by`;
ALTER TABLE `#__askdesk_rooms` ADD COLUMN `reminded` datetime NULL DEFAULT NULL AFTER `alerted`;
ALTER TABLE `#__askdesk_rooms` ADD KEY `idx_settled` (`settled`);
UPDATE `#__askdesk_rooms` AS r SET r.`last_author` = COALESCE((SELECT m.`author` FROM `#__askdesk_messages` AS m WHERE m.`room_id` = r.`id` AND m.`author` <> 'system' ORDER BY m.`id` DESC LIMIT 1), '');
-- What ended before: closed, unless the visitor's last words went unanswered
UPDATE `#__askdesk_rooms` SET `settled` = COALESCE(`closed`, `modified`) WHERE `status` = 'closed' AND `last_author` <> 'visitor';
