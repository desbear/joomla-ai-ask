-- 0.6.2.3: a message left marked as a ticket (seen by its visitor after it is closed too)
ALTER TABLE "#__askdesk_rooms" ADD COLUMN IF NOT EXISTS "ticket" smallint DEFAULT 0 NOT NULL;
UPDATE "#__askdesk_rooms" SET "ticket" = 1 WHERE "status" = 'message';
-- and one-time codes a visitor asks for to see their tickets (the address and the code hashed; kept minutes)
CREATE TABLE IF NOT EXISTS "#__askdesk_codes" (
  "id" serial NOT NULL,
  "email" char(64) NOT NULL,
  "code" char(64) NOT NULL,
  "ip" char(64) DEFAULT '' NOT NULL,
  "tries" smallint DEFAULT 0 NOT NULL,
  "created" timestamp without time zone NOT NULL,
  "expires" timestamp without time zone NOT NULL,
  PRIMARY KEY ("id")
);
CREATE INDEX IF NOT EXISTS "#__askdesk_codes_idx_email" ON "#__askdesk_codes" ("email");
CREATE INDEX IF NOT EXISTS "#__askdesk_codes_idx_created" ON "#__askdesk_codes" ("created");
