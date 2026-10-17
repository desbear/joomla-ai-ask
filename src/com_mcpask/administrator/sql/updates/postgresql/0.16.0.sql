CREATE TABLE IF NOT EXISTS "#__mcpask_urls" (
  "id" serial NOT NULL,
  "url" varchar(1024) NOT NULL,
  "url_hash" char(40) NOT NULL,
  "selector" varchar(200) DEFAULT '' NOT NULL,
  "created" timestamp without time zone NOT NULL,
  "created_by" integer DEFAULT 0 NOT NULL,
  PRIMARY KEY ("id"),
  CONSTRAINT "#__mcpask_urls_idx_url" UNIQUE ("url_hash")
);

CREATE TABLE IF NOT EXISTS "#__mcpask_ignores" (
  "id" serial NOT NULL,
  "source" varchar(32) NOT NULL,
  "item_id" integer NOT NULL,
  "title" varchar(255) DEFAULT '' NOT NULL,
  "url" varchar(1024) DEFAULT '' NOT NULL,
  "note" varchar(255) DEFAULT '' NOT NULL,
  "created" timestamp without time zone NOT NULL,
  "created_by" integer DEFAULT 0 NOT NULL,
  PRIMARY KEY ("id"),
  CONSTRAINT "#__mcpask_ignores_idx_item" UNIQUE ("source", "item_id")
);
