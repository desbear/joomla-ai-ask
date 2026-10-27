-- 0.6.2.2: a guest's identity confirmed by the person answering (asked them first); until then what is known of
-- someone with the e-mail they typed is not shown as theirs
ALTER TABLE `#__askdesk_rooms` ADD COLUMN `identity_confirmed` datetime NULL DEFAULT NULL;
ALTER TABLE `#__askdesk_rooms` ADD COLUMN `identity_by` int unsigned NOT NULL DEFAULT 0;
