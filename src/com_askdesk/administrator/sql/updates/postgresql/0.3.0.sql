-- 0.3.0: browsers to notify (Web Push), and the site's keys for it
CREATE TABLE IF NOT EXISTS "#__askdesk_push" (
  "id" serial NOT NULL,
  "user_id" integer DEFAULT 0 NOT NULL,
  "endpoint" text NOT NULL,
  "endpoint_hash" char(64) NOT NULL,
  "p256dh" varchar(100) DEFAULT '' NOT NULL,
  "auth" varchar(40) DEFAULT '' NOT NULL,
  "created" timestamp without time zone NOT NULL,
  "last_sent" timestamp without time zone,
  "failures" integer DEFAULT 0 NOT NULL,
  PRIMARY KEY ("id")
);
CREATE UNIQUE INDEX "#__askdesk_push_idx_endpoint" ON "#__askdesk_push" ("endpoint_hash");
CREATE INDEX "#__askdesk_push_idx_user" ON "#__askdesk_push" ("user_id");

CREATE TABLE IF NOT EXISTS "#__askdesk_secrets" (
  "name" varchar(50) NOT NULL,
  "value" text NOT NULL,
  PRIMARY KEY ("name")
);
