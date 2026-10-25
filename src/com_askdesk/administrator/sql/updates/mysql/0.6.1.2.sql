-- 0.6.1.2: indexes for what every poll asks (how many each list has; when each chat was last written in)
-- and for clearing old chats
ALTER TABLE `#__askdesk_rooms` ADD KEY `idx_open` (`status`, `settled`);
ALTER TABLE `#__askdesk_rooms` ADD KEY `idx_created` (`created`);
ALTER TABLE `#__askdesk_messages` ADD KEY `idx_room_author` (`room_id`, `author`, `id`);
