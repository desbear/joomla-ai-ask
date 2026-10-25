-- 0.6.1.3: the chats of one customer found by their address (the side cards ask it for each chat opened)
ALTER TABLE `#__askdesk_rooms` ADD KEY `idx_email` (`email`(191));
