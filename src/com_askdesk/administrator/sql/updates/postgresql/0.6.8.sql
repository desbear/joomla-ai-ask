-- 0.6.8: the sticker packs, each a block of its own: their order and the sticker shown for them (the stickers
-- keep naming their pack; a pack not yet here is added when the stickers are next looked at)
CREATE TABLE IF NOT EXISTS "#__askdesk_sticker_packs" (
  "id" serial NOT NULL,
  "name" varchar(100) DEFAULT '' NOT NULL,
  "cover" integer DEFAULT 0 NOT NULL,
  "ordering" integer DEFAULT 0 NOT NULL,
  PRIMARY KEY ("id")
);
CREATE UNIQUE INDEX IF NOT EXISTS "#__askdesk_sticker_packs_idx_name" ON "#__askdesk_sticker_packs" ("name");
