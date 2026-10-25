ALTER TABLE "#__mcpask_log" ADD COLUMN IF NOT EXISTS "question_raw" text;
ALTER TABLE "#__mcpask_log" ADD COLUMN IF NOT EXISTS "evidence" smallint DEFAULT 0 NOT NULL;
CREATE INDEX IF NOT EXISTS "#__mcpask_log_idx_evidence_created" ON "#__mcpask_log" ("evidence", "created");
CREATE INDEX IF NOT EXISTS "#__mcpask_agent_events_idx_created" ON "#__mcpask_agent_events" ("created");
UPDATE "#__mcpask_log" SET "evidence" = 1 WHERE "status" = 'blocked' OR "flags" LIKE '%probe%' OR "flags" LIKE '%injection_suspect%' OR "flags" LIKE '%html_payload%' OR "flags" LIKE '%identity_refused%';
