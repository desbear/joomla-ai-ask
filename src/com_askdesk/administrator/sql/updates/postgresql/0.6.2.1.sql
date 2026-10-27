-- 0.6.2.1: records changed from the dashboard, told to the others looking at them (which, not what; kept an hour)
CREATE TABLE IF NOT EXISTS "#__askdesk_source_changes" (
  "id" serial NOT NULL,
  "source" varchar(30) NOT NULL,
  "template" varchar(100) NOT NULL,
  "record" varchar(100) NOT NULL,
  "user_id" integer DEFAULT 0 NOT NULL,
  "deleted" smallint DEFAULT 0 NOT NULL,
  "created" timestamp without time zone NOT NULL,
  PRIMARY KEY ("id")
);
CREATE INDEX IF NOT EXISTS "#__askdesk_source_changes_idx_created" ON "#__askdesk_source_changes" ("created");
