-- 0.6.1: work cards in a chat (a form filled where only the people answering see it)
CREATE TABLE IF NOT EXISTS "#__askdesk_work" (
  "id" serial NOT NULL,
  "room_id" integer NOT NULL,
  "staff_id" integer DEFAULT 0 NOT NULL,
  "panel" varchar(30) NOT NULL,
  "form" varchar(40) NOT NULL,
  "card" text NOT NULL,
  "state" varchar(10) DEFAULT 'open' NOT NULL,
  "created" timestamp without time zone NOT NULL,
  "modified" timestamp without time zone NOT NULL,
  PRIMARY KEY ("id")
);
CREATE INDEX IF NOT EXISTS "#__askdesk_work_idx_room" ON "#__askdesk_work" ("room_id");
