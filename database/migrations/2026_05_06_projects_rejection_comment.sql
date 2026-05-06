ALTER TABLE projects
ADD COLUMN rejection_comment TEXT NULL AFTER approval_status;
