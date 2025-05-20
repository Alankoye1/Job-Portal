<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Set a message to be displayed to the user
 * 
 * @param string $message The message to display
 * @param string $type The type of message (success, danger, warning, info)
 */
function setMessage($message, $type = 'info') {
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $type;
}

/**
 * Check if user is logged in
 * 
 * @return bool True if user is logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if user is an employer
 * 
 * @return bool True if user is an employer, false otherwise
 */
function isEmployer() {
    return isLoggedIn() && $_SESSION['user_type'] == 'employer';
}

/**
 * Check if user is a job seeker
 * 
 * @return bool True if user is a job seeker, false otherwise
 */
function isJobSeeker() {
    return isLoggedIn() && $_SESSION['user_type'] == 'jobseeker';
}

/**
 * Redirect user to login page if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        setMessage('Please log in to access this page.', 'warning');
        header('Location: /auth/login.php');
        exit;
    }
}

/**
 * Redirect user if not an employer
 */
function requireEmployer() {
    requireLogin();
    if (!isEmployer()) {
        setMessage('Access denied. Employer account required.', 'danger');
        if (isJobSeeker()) {
            header('Location: /jobseeker/dashboard.php');
        } else {
            header('Location: /index.php');
        }
        exit;
    }
}

/**
 * Redirect user if not a job seeker
 */
function requireJobSeeker() {
    requireLogin();
    if (!isJobSeeker()) {
        setMessage('Access denied. Job seeker account required.', 'danger');
        if (isEmployer()) {
            header('Location: /employer/dashboard.php');
        } else {
            header('Location: /index.php');
        }
        exit;
    }
}

/**
 * Format date to a readable format
 * 
 * @param string $date The date to format
 * @param string $format The format to use
 * @return string The formatted date
 */
function formatDate($date, $format = 'M j, Y') {
    $timestamp = strtotime($date);
    return date($format, $timestamp);
}

/**
 * Calculate time ago from a given date
 * 
 * @param string $date The date to calculate time ago from
 * @return string The time ago string (e.g. "2 hours ago", "3 days ago")
 */
function timeAgo($date) {
    $timestamp = strtotime($date);
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 2592000) {
        $weeks = floor($diff / 604800);
        return $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
    } else {
        return formatDate($date);
    }
}

/**
 * Generate a random token
 * 
 * @param int $length The length of the token
 * @return string The generated token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Upload a file
 * 
 * @param array $file The file to upload ($_FILES['input_name'])
 * @param string $destination The destination directory
 * @param array $allowed_types Allowed file types
 * @param int $max_size Maximum file size in bytes
 * @return array Status and message/filename
 */
function uploadFile($file, $destination, $allowed_types = ['pdf', 'doc', 'docx'], $max_size = 5242880) {
    // Check if file was uploaded without errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form',
            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
        ];
        
        $error_message = isset($error_messages[$file['error']]) 
            ? $error_messages[$file['error']] 
            : 'Unknown upload error';
            
        return ['status' => false, 'message' => $error_message];
    }
    
    // Check file size
    if ($file['size'] > $max_size) {
        return ['status' => false, 'message' => 'File size exceeds the maximum limit of ' . ($max_size / 1048576) . 'MB'];
    }
    
    // Validate file type
    $file_info = pathinfo($file['name']);
    $file_ext = strtolower($file_info['extension']);
    
    if (!in_array($file_ext, $allowed_types)) {
        return [
            'status' => false, 
            'message' => 'Invalid file type. Allowed types: ' . implode(', ', $allowed_types)
        ];
    }
    
    // Create destination directory if it doesn't exist
    if (!file_exists($destination)) {
        mkdir($destination, 0777, true);
    }
    
    // Generate a unique filename
    $filename = uniqid() . '_' . bin2hex(random_bytes(8)) . '.' . $file_ext;
    $filepath = $destination . '/' . $filename;
    
    // Move the uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['status' => true, 'filename' => $filename, 'path' => $filepath];
    } else {
        return ['status' => false, 'message' => 'Failed to move uploaded file'];
    }
}

/**
 * Get list of job categories
 * 
 * @return array List of job categories
 */
function getJobCategories() {
    return [
        'technology' => 'Technology & IT',
        'healthcare' => 'Healthcare & Medical',
        'education' => 'Education & Training',
        'finance' => 'Finance & Banking',
        'marketing' => 'Marketing & Sales',
        'engineering' => 'Engineering & Construction',
        'creative' => 'Creative & Design',
        'hospitality' => 'Hospitality & Tourism',
        'legal' => 'Legal & Compliance',
        'administrative' => 'Administrative & Support',
        'retail' => 'Retail & Customer Service',
        'manufacturing' => 'Manufacturing & Production',
        'transport' => 'Transport & Logistics',
        'hr' => 'Human Resources',
        'other' => 'Other'
    ];
}

/**
 * Get list of employment types
 * 
 * @return array List of employment types
 */
function getEmploymentTypes() {
    return [
        'full-time' => 'Full-Time',
        'part-time' => 'Part-Time',
        'contract' => 'Contract',
        'temporary' => 'Temporary',
        'internship' => 'Internship',
        'remote' => 'Remote',
        'freelance' => 'Freelance'
    ];
}

/**
 * Get list of experience levels
 * 
 * @return array List of experience levels
 */
function getExperienceLevels() {
    return [
        'intermediate' => 'Intermediate',
        'experienced' => 'Experienced',
        'manager' => 'Manager',
        'director' => 'Director',
        'executive' => 'Executive'
    ];
}

/**
 * Get list of education levels
 * 
 * @return array List of education levels
 */
function getEducationLevels() {
    return [
        'high-school' => 'High School',
        'associate' => 'Associate Degree',
        'bachelor' => 'Bachelor\'s Degree',
        'master' => 'Master\'s Degree',
        'doctorate' => 'Doctorate',
        'professional' => 'Professional Certification',
        'any' => 'Any Education Level'
    ];
}

/**
 * Truncate text to a specified length
 * 
 * @param string $text The text to truncate
 * @param int $length The maximum length
 * @param string $append Text to append if truncated
 * @return string The truncated text
 */
function truncateText($text, $length = 100, $append = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    
    $text = substr($text, 0, $length);
    $text = substr($text, 0, strrpos($text, ' '));
    
    return $text . $append;
}
?>