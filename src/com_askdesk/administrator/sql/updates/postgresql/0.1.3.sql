-- 0.1.3: phrases the people answering often write
CREATE TABLE IF NOT EXISTS "#__askdesk_phrases" (
  "id" serial NOT NULL,
  "user_id" integer DEFAULT 0 NOT NULL,
  "title" varchar(60) DEFAULT '' NOT NULL,
  "text" text NOT NULL,
  "pinned" smallint DEFAULT 0 NOT NULL,
  "published" smallint DEFAULT 1 NOT NULL,
  "ordering" integer DEFAULT 0 NOT NULL,
  "created" timestamp without time zone NOT NULL,
  PRIMARY KEY ("id")
);
CREATE INDEX "#__askdesk_phrases_idx_user" ON "#__askdesk_phrases" ("user_id", "published", "ordering");
