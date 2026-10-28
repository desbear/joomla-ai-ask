CREATE TABLE IF NOT EXISTS "#__askdesk_rooms" (
  "id" serial NOT NULL,
  "token" char(64) NOT NULL,
  "agent_id" integer DEFAULT 0 NOT NULL,
  "status" varchar(10) DEFAULT 'waiting' NOT NULL,
  "staff_id" integer DEFAULT 0 NOT NULL,
  "user_id" integer DEFAULT 0 NOT NULL,
  "name" varchar(100) DEFAULT '' NOT NULL,
  "email" varchar(255) DEFAULT '' NOT NULL,
  "company" varchar(150) DEFAULT '' NOT NULL,
  "page_language" varchar(10) DEFAULT '' NOT NULL,
  "visitor_language" varchar(10) DEFAULT '' NOT NULL,
  "staff_language" varchar(10) DEFAULT '' NOT NULL,
  "page" varchar(500) DEFAULT '' NOT NULL,
  "entry" text NOT NULL,
  "last_message" integer DEFAULT 0 NOT NULL,
  "last_author" varchar(10) DEFAULT '' NOT NULL,
  "staff_seen" integer DEFAULT 0 NOT NULL,
  "created" timestamp without time zone NOT NULL,
  "modified" timestamp without time zone NOT NULL,
  "visitor_seen" timestamp without time zone,
  "visitor_typing" timestamp without time zone,
  "staff_typing" timestamp without time zone,
  "closed" timestamp without time zone,
  "settled" timestamp without time zone,
  "settled_by" integer DEFAULT 0 NOT NULL,
  "alerted" timestamp without time zone,
  "reminded" timestamp without time zone,
  "archived_at" timestamp without time zone DEFAULT NULL,
  "identity_confirmed" timestamp without time zone DEFAULT NULL,
  "identity_by" integer DEFAULT 0 NOT NULL,
  "ticket" smallint DEFAULT 0 NOT NULL,
  PRIMARY KEY ("id"),
  CONSTRAINT "#__askdesk_rooms_idx_token" UNIQUE ("token")
);
CREATE INDEX "#__askdesk_rooms_idx_status" ON "#__askdesk_rooms" ("status", "modified");
CREATE INDEX "#__askdesk_rooms_idx_user" ON "#__askdesk_rooms" ("user_id");
CREATE INDEX "#__askdesk_rooms_idx_staff" ON "#__askdesk_rooms" ("staff_id", "status");
CREATE INDEX "#__askdesk_rooms_idx_settled" ON "#__askdesk_rooms" ("settled");
CREATE INDEX "#__askdesk_rooms_idx_open" ON "#__askdesk_rooms" ("status", "settled");
CREATE INDEX "#__askdesk_rooms_idx_created" ON "#__askdesk_rooms" ("created");
CREATE INDEX "#__askdesk_rooms_idx_email" ON "#__askdesk_rooms" (LOWER("email"));
CREATE INDEX "#__askdesk_rooms_idx_archived" ON "#__askdesk_rooms" ("archived_at");

CREATE TABLE IF NOT EXISTS "#__askdesk_messages" (
  "id" serial NOT NULL,
  "room_id" integer NOT NULL,
  "author" varchar(10) NOT NULL,
  "staff_id" integer DEFAULT 0 NOT NULL,
  "text" text NOT NULL,
  "language" varchar(10) DEFAULT '' NOT NULL,
  "translation" text NOT NULL,
  "translation_language" varchar(10) DEFAULT '' NOT NULL,
  "suggestions" text,
  "links" text,
  "file_id" integer DEFAULT 0 NOT NULL,
  "sticker_id" integer DEFAULT 0 NOT NULL,
  "translating" smallint DEFAULT 0 NOT NULL,
  "created" timestamp without time zone NOT NULL,
  PRIMARY KEY ("id")
);
CREATE INDEX "#__askdesk_messages_idx_room" ON "#__askdesk_messages" ("room_id", "id");
CREATE INDEX "#__askdesk_messages_idx_room_author" ON "#__askdesk_messages" ("room_id", "author", "id");

CREATE TABLE IF NOT EXISTS "#__askdesk_staff" (
  "user_id" integer NOT NULL,
  "active" smallint DEFAULT 0 NOT NULL,
  "status" varchar(10) DEFAULT 'away' NOT NULL,
  "language" varchar(10) DEFAULT '' NOT NULL,
  "seen" timestamp without time zone,
  "display_name" varchar(100) DEFAULT '' NOT NULL,
  "avatar" varchar(100) DEFAULT '' NOT NULL,
  "avatar_image" varchar(500) DEFAULT '' NOT NULL,
  "thanks_image" varchar(500) DEFAULT '' NOT NULL,
  PRIMARY KEY ("user_id")
);

CREATE TABLE IF NOT EXISTS "#__askdesk_files" (
  "id" serial NOT NULL,
  "room_id" integer NOT NULL,
  "token" char(32) NOT NULL,
  "name" varchar(160) DEFAULT '' NOT NULL,
  "kind" varchar(10) DEFAULT 'document' NOT NULL,
  "size" integer DEFAULT 0 NOT NULL,
  "saved_as" varchar(64) DEFAULT '' NOT NULL,
  "author" varchar(10) DEFAULT 'visitor' NOT NULL,
  "staff_id" integer DEFAULT 0 NOT NULL,
  "created" timestamp without time zone NOT NULL,
  "expires" timestamp without time zone NOT NULL,
  PRIMARY KEY ("id"),
  CONSTRAINT "#__askdesk_files_idx_token" UNIQUE ("token")
);
CREATE INDEX "#__askdesk_files_idx_room" ON "#__askdesk_files" ("room_id");

CREATE TABLE IF NOT EXISTS "#__askdesk_handled" (
  "id" serial NOT NULL,
  "room_id" integer NOT NULL,
  "staff_id" integer NOT NULL,
  "kind" varchar(10) DEFAULT 'chat' NOT NULL,
  "member" smallint DEFAULT 0 NOT NULL,
  "waited" integer DEFAULT 0 NOT NULL,
  "created" timestamp without time zone NOT NULL,
  PRIMARY KEY ("id"),
  CONSTRAINT "#__askdesk_handled_idx_room" UNIQUE ("room_id")
);
CREATE INDEX "#__askdesk_handled_idx_staff" ON "#__askdesk_handled" ("staff_id", "created");

CREATE TABLE IF NOT EXISTS "#__askdesk_ratings" (
  "id" serial NOT NULL,
  "room_id" integer NOT NULL,
  "staff_id" integer DEFAULT 0 NOT NULL,
  "agent_id" integer DEFAULT 0 NOT NULL,
  "member" smallint DEFAULT 0 NOT NULL,
  "language" varchar(10) DEFAULT '' NOT NULL,
  "ratings" text NOT NULL,
  "comment" text NOT NULL,
  "created" timestamp without time zone NOT NULL,
  PRIMARY KEY ("id"),
  CONSTRAINT "#__askdesk_ratings_idx_room" UNIQUE ("room_id")
);
CREATE INDEX "#__askdesk_ratings_idx_created" ON "#__askdesk_ratings" ("created");

CREATE TABLE IF NOT EXISTS "#__askdesk_stickers" (
  "id" serial NOT NULL,
  "pack" varchar(100) DEFAULT '' NOT NULL,
  "title" varchar(100) DEFAULT '' NOT NULL,
  "image" varchar(255) DEFAULT '' NOT NULL,
  "who" varchar(10) DEFAULT 'all' NOT NULL,
  "published" smallint DEFAULT 1 NOT NULL,
  "ordering" integer DEFAULT 0 NOT NULL,
  "created" timestamp without time zone NOT NULL,
  PRIMARY KEY ("id")
);
CREATE INDEX "#__askdesk_stickers_idx_pack" ON "#__askdesk_stickers" ("published", "pack", "ordering");

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

CREATE TABLE IF NOT EXISTS "#__askdesk_boards" (
  "user_id" integer NOT NULL,
  "layout" text NOT NULL,
  "updated" timestamp without time zone NOT NULL,
  PRIMARY KEY ("user_id")
);

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
CREATE INDEX "#__askdesk_source_changes_idx_created" ON "#__askdesk_source_changes" ("created");

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
CREATE INDEX "#__askdesk_codes_idx_email" ON "#__askdesk_codes" ("email");
CREATE INDEX "#__askdesk_codes_idx_created" ON "#__askdesk_codes" ("created");

-- The e-mails the desk sends, as designed in the administrator, one for each language (0.6.3)
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
CREATE UNIQUE INDEX "#__askdesk_mails_idx_kind_language" ON "#__askdesk_mails" ("kind", "language");
