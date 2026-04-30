-- Add installed_version column to user_apps table
ALTER TABLE user_apps 
ADD COLUMN installed_version VARCHAR(20) DEFAULT NULL AFTER installed_at;

-- Update existing records to set default version
UPDATE user_apps 
SET installed_version = '1.0.0' 
WHERE installed_version IS NULL;
