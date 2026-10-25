-- 0.6.2: closed chats archived (written out of the site, never read back) and only then deleted: when each was
ALTER TABLE "#__askdesk_rooms" ADD COLUMN IF NOT EXISTS "archived_at" timestamp without time zone DEFAULT NULL;
CREATE INDEX IF NOT EXISTS "#__askdesk_rooms_idx_archived" ON "#__askdesk_rooms" ("archived_at");
