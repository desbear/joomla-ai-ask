ALTER TABLE `#__mcpask_log` ADD COLUMN `question_raw` text NULL AFTER `question`;
ALTER TABLE `#__mcpask_log` ADD COLUMN `evidence` tinyint NOT NULL DEFAULT 0 AFTER `flags`;
ALTER TABLE `#__mcpask_log` ADD KEY `idx_evidence_created` (`evidence`, `created`);
ALTER TABLE `#__mcpask_agent_events` ADD KEY `idx_created` (`created`);
UPDATE `#__mcpask_log` SET `evidence` = 1 WHERE `status` = 'blocked' OR `flags` LIKE '%probe%' OR `flags` LIKE '%injection_suspect%' OR `flags` LIKE '%html_payload%' OR `flags` LIKE '%identity_refused%';
