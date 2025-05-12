-- Create employers table
CREATE TABLE IF NOT EXISTS `employers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `industry` varchar(100) DEFAULT NULL,
  `company_size` varchar(50) DEFAULT NULL,
  `founded_year` int(4) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create jobs table if it doesn't exist
CREATE TABLE IF NOT EXISTS `jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employer_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `job_type` varchar(50) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `description` text NOT NULL,
  `requirements` text DEFAULT NULL,
  `responsibilities` text DEFAULT NULL,
  `salary_min` decimal(10,2) DEFAULT NULL,
  `salary_max` decimal(10,2) DEFAULT NULL,
  `salary_period` varchar(20) DEFAULT NULL,
  `benefits` text DEFAULT NULL,
  `application_url` varchar(255) DEFAULT NULL,
  `featured` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('draft','active','expired','closed') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `employer_id` (`employer_id`),
  CONSTRAINT `jobs_ibfk_1` FOREIGN KEY (`employer_id`) REFERENCES `employers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create jobseekers table
CREATE TABLE IF NOT EXISTS `jobseekers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `resume` varchar(255) DEFAULT NULL,
  `profile_pic` varchar(255) DEFAULT NULL,
  `headline` varchar(255) DEFAULT NULL,
  `summary` text DEFAULT NULL,
  `skills` text DEFAULT NULL,
  `experience_years` int(2) DEFAULT NULL,
  `education_level` varchar(100) DEFAULT NULL,
  `current_position` varchar(255) DEFAULT NULL,
  `current_company` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create applications table
CREATE TABLE IF NOT EXISTS `applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `job_id` int(11) NOT NULL,
  `jobseeker_id` int(11) NOT NULL,
  `cover_letter` text DEFAULT NULL,
  `resume` varchar(255) DEFAULT NULL,
  `status` enum('pending','reviewed','shortlisted','rejected','interviewed','offered','hired','withdrawn') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `job_id` (`job_id`),
  KEY `jobseeker_id` (`jobseeker_id`),
  CONSTRAINT `applications_ibfk_1` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `applications_ibfk_2` FOREIGN KEY (`jobseeker_id`) REFERENCES `jobseekers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert sample data into employers
INSERT INTO `employers` (`company_name`, `email`, `password`, `website`, `description`, `location`, `industry`, `company_size`) VALUES
('TechCorp', 'hr@techcorp.com', '$2y$10$xyz123', 'https://techcorp.example.com', 'Leading technology company', 'San Francisco, CA', 'technology', '500-1000'),
('Healthcare Plus', 'careers@healthcareplus.com', '$2y$10$xyz456', 'https://healthcareplus.example.com', 'Healthcare provider', 'Boston, MA', 'healthcare', '1000+'),
('EduLearn', 'jobs@edulearn.com', '$2y$10$xyz789', 'https://edulearn.example.com', 'Online education platform', 'New York, NY', 'education', '100-500');

-- Insert sample data into jobs
INSERT INTO `jobs` (`employer_id`, `title`, `location`, `job_type`, `category`, `description`, `requirements`, `salary_min`, `salary_max`, `salary_period`, `featured`, `status`, `expires_at`) VALUES
(1, 'Senior Software Engineer', 'San Francisco, CA', 'full_time', 'technology', 'Join our team as a Senior Software Engineer...', 'At least 5 years experience in software development...', 120000.00, 150000.00, 'year', 1, 'active', DATE_ADD(NOW(), INTERVAL 30 DAY)),
(1, 'Product Manager', 'San Francisco, CA', 'full_time', 'technology', 'We are looking for an experienced Product Manager...', 'At least 3 years of product management experience...', 100000.00, 130000.00, 'year', 0, 'active', DATE_ADD(NOW(), INTERVAL 30 DAY)),
(2, 'Registered Nurse', 'Boston, MA', 'full_time', 'healthcare', 'Join our team of healthcare professionals...', 'Valid nursing license and 2+ years experience...', 70000.00, 90000.00, 'year', 1, 'active', DATE_ADD(NOW(), INTERVAL 30 DAY)),
(3, 'Online Tutor - Mathematics', 'Remote', 'part_time', 'education', 'Teach mathematics to students online...', 'Bachelor\'s degree in Mathematics or related field...', 25.00, 35.00, 'hour', 1, 'active', DATE_ADD(NOW(), INTERVAL 30 DAY));

-- Insert sample data into jobseekers
INSERT INTO `jobseekers` (`first_name`, `last_name`, `email`, `password`, `location`, `headline`, `skills`, `experience_years`) VALUES
('John', 'Doe', 'john.doe@example.com', '$2y$10$abc123', 'New York, NY', 'Software Developer with 5+ years experience', 'JavaScript, PHP, MySQL, React', 5),
('Jane', 'Smith', 'jane.smith@example.com', '$2y$10$def456', 'Chicago, IL', 'Marketing Professional', 'Digital Marketing, Social Media, Content Strategy', 8),
('Michael', 'Johnson', 'michael.j@example.com', '$2y$10$ghi789', 'Austin, TX', 'UX/UI Designer', 'Figma, Adobe XD, User Testing, Prototyping', 3);

-- Insert sample applications
INSERT INTO `applications` (`job_id`, `jobseeker_id`, `status`, `created_at`) VALUES
(1, 1, 'shortlisted', NOW()),
(2, 1, 'pending', NOW()),
(3, 2, 'reviewed', NOW()),
(4, 3, 'interviewed', NOW());
