-- 0.6.2: closed chats archived (written out of the site, never read back) and only then deleted: when each was
ALTER TABLE `#__askdesk_rooms` ADD COLUMN `archived_at` datetime NULL DEFAULT NULL;
ALTER TABLE `#__askdesk_rooms` ADD KEY `idx_archived` (`archived_at`);
