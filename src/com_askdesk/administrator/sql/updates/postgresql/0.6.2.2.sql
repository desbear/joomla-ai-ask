-- 0.6.2.2: a guest's identity confirmed by the person answering (asked them first)
ALTER TABLE "#__askdesk_rooms" ADD COLUMN IF NOT EXISTS "identity_confirmed" timestamp without time zone DEFAULT NULL;
ALTER TABLE "#__askdesk_rooms" ADD COLUMN IF NOT EXISTS "identity_by" integer DEFAULT 0 NOT NULL;
