-- 0.6.1.2: indexes for what every poll asks (how many each list has; when each chat was last written in)
-- and for clearing old chats
CREATE INDEX IF NOT EXISTS "#__askdesk_rooms_idx_open" ON "#__askdesk_rooms" ("status", "settled");
CREATE INDEX IF NOT EXISTS "#__askdesk_rooms_idx_created" ON "#__askdesk_rooms" ("created");
CREATE INDEX IF NOT EXISTS "#__askdesk_messages_idx_room_author" ON "#__askdesk_messages" ("room_id", "author", "id");
