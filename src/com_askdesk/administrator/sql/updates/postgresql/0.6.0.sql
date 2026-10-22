-- 0.6.0: who wrote last in each chat; a chat closed (結案) by someone, or left to be; the e-mails about it
ALTER TABLE "#__askdesk_rooms" ADD COLUMN "last_author" varchar(10) NOT NULL DEFAULT '';
ALTER TABLE "#__askdesk_rooms" ADD COLUMN "settled" timestamp without time zone;
ALTER TABLE "#__askdesk_rooms" ADD COLUMN "settled_by" integer NOT NULL DEFAULT 0;
ALTER TABLE "#__askdesk_rooms" ADD COLUMN "alerted" timestamp without time zone;
ALTER TABLE "#__askdesk_rooms" ADD COLUMN "reminded" timestamp without time zone;
CREATE INDEX "#__askdesk_rooms_idx_settled" ON "#__askdesk_rooms" ("settled");
UPDATE "#__askdesk_rooms" AS r SET "last_author" = COALESCE((SELECT m."author" FROM "#__askdesk_messages" AS m WHERE m."room_id" = r."id" AND m."author" <> 'system' ORDER BY m."id" DESC LIMIT 1), '');
-- What ended before: closed, unless the visitor's last words went unanswered
UPDATE "#__askdesk_rooms" SET "settled" = COALESCE("closed", "modified") WHERE "status" = 'closed' AND "last_author" <> 'visitor';
