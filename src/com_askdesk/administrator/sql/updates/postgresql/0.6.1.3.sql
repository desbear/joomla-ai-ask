-- 0.6.1.3: the chats of one customer found by their address (the side cards ask it for each chat opened)
CREATE INDEX IF NOT EXISTS "#__askdesk_rooms_idx_email" ON "#__askdesk_rooms" (LOWER("email"));
