-- 0.5.0: each person's dashboard
CREATE TABLE IF NOT EXISTS "#__askdesk_boards" (
  "user_id" integer NOT NULL,
  "layout" text NOT NULL,
  "updated" timestamp without time zone NOT NULL,
  PRIMARY KEY ("user_id")
);
