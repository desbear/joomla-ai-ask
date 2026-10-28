-- 0.6.3: the e-mails the desk sends, as designed in the administrator, one for each language
CREATE TABLE IF NOT EXISTS "#__askdesk_mails" (
  "id" serial NOT NULL,
  "kind" varchar(20) NOT NULL,
  "language" varchar(10) NOT NULL,
  "subject" varchar(255) DEFAULT '' NOT NULL,
  "html" text NOT NULL,
  "design" text NOT NULL,
  "published" smallint DEFAULT 1 NOT NULL,
  "modified" timestamp without time zone NOT NULL,
  "modified_by" integer DEFAULT 0 NOT NULL,
  PRIMARY KEY ("id")
);
CREATE UNIQUE INDEX IF NOT EXISTS "#__askdesk_mails_idx_kind_language" ON "#__askdesk_mails" ("kind", "language");
