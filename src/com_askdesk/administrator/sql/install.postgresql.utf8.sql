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
  "staff_seen" integer DEFAULT 0 NOT NULL,
  "created" timestamp without time zone NOT NULL,
  "modified" timestamp without time zone NOT NULL,
  "visitor_seen" timestamp without time zone,
  "visitor_typing" timestamp without time zone,
  "staff_typing" timestamp without time zone,
  "closed" timestamp without time zone,
  PRIMARY KEY ("id"),
  CONSTRAINT "#__askdesk_rooms_idx_token" UNIQUE ("token")
);
CREATE INDEX "#__askdesk_rooms_idx_status" ON "#__askdesk_rooms" ("status", "modified");
CREATE INDEX "#__askdesk_rooms_idx_user" ON "#__askdesk_rooms" ("user_id");
CREATE INDEX "#__askdesk_rooms_idx_staff" ON "#__askdesk_rooms" ("staff_id", "status");

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
