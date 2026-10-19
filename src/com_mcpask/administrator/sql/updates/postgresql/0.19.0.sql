CREATE TABLE IF NOT EXISTS "#__mcpask_texts" (
  "source" varchar(32) NOT NULL,
  "item_id" integer NOT NULL,
  "text" text NOT NULL,
  "mode" varchar(8) DEFAULT 'add' NOT NULL,
  "modified" timestamp without time zone NOT NULL,
  "modified_by" integer DEFAULT 0 NOT NULL,
  PRIMARY KEY ("source", "item_id")
);

ALTER TABLE "#__mcpask_pets" ADD COLUMN IF NOT EXISTS "ordering" integer DEFAULT 0 NOT NULL;
