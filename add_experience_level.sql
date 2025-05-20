-- Add experience_level column to jobs table
ALTER TABLE jobs ADD COLUMN experience_level VARCHAR(20) DEFAULT NULL COMMENT 'Job experience level requirement';

-- Update existing jobs with sample values
UPDATE jobs SET experience_level = 'entry' WHERE RAND() < 0.2;
UPDATE jobs SET experience_level = 'intermediate' WHERE RAND() < 0.3 AND experience_level IS NULL;
UPDATE jobs SET experience_level = 'experienced' WHERE RAND() < 0.4 AND experience_level IS NULL;
UPDATE jobs SET experience_level = 'manager' WHERE RAND() < 0.5 AND experience_level IS NULL;
UPDATE jobs SET experience_level = 'director' WHERE RAND() < 0.6 AND experience_level IS NULL;
UPDATE jobs SET experience_level = 'executive' WHERE experience_level IS NULL;

-- Add an index for faster filtering
ALTER TABLE jobs ADD INDEX idx_experience_level (experience_level); 