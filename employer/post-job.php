<?php
// Set page title
$page_title = "Post a Job";
$page_header = "Create a New Job Listing";

// Include database connection
require_once '../config/db.php';
// Include functions
require_once '../includes/functions.php';

// Check if user is logged in and is an employer
requireEmployer();

// Get employer information
$query = "SELECT * FROM employers WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$employer = $result->fetch_assoc();

// Process job posting form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $title = sanitizeInput($_POST['title']);
    $description = sanitizeInput($_POST['description']);
    $responsibilities = isset($_POST['responsibilities']) ? sanitizeInput($_POST['responsibilities']) : '';
    $requirements = isset($_POST['requirements']) ? sanitizeInput($_POST['requirements']) : '';
    $benefits = isset($_POST['benefits']) ? sanitizeInput($_POST['benefits']) : '';
    $location = sanitizeInput($_POST['location']);
    $job_type = sanitizeInput($_POST['job_type']);
    $category = sanitizeInput($_POST['category']);
    $experience_level = sanitizeInput($_POST['experience_level']);
    $education_level = sanitizeInput($_POST['education_level']);
    $salary_min = !empty($_POST['salary_min']) ? (float) $_POST['salary_min'] : null;
    $salary_max = !empty($_POST['salary_max']) ? (float) $_POST['salary_max'] : null;
    $salary_period = !empty($_POST['salary_period']) ? sanitizeInput($_POST['salary_period']) : null;
    $featured = isset($_POST['featured']) ? 1 : 0;
    $status = isset($_POST['save_as_draft']) ? 'draft' : 'active';
    
    // Calculate expiration date (30 days from now by default)
    $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));
    
    // Validate inputs
    $errors = [];
    
    if (empty($title)) {
        $errors[] = "Job title is required";
    }
    
    if (empty($description)) {
        $errors[] = "Job description is required";
    }
    
    if (empty($job_type)) {
        $errors[] = "Job type is required";
    }
    
    if (empty($category)) {
        $errors[] = "Job category is required";
    }
    
    if (!empty($salary_min) && !empty($salary_max) && $salary_min > $salary_max) {
        $errors[] = "Minimum salary cannot be greater than maximum salary";
    }
    
    // Create jobs table if it doesn't exist
    $create_jobs_table = "CREATE TABLE IF NOT EXISTS jobs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employer_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        responsibilities TEXT,
        requirements TEXT,
        benefits TEXT,
        location VARCHAR(255),
        salary_min DECIMAL(10,2),
        salary_max DECIMAL(10,2),
        salary_period ENUM('hourly', 'daily', 'weekly', 'monthly', 'yearly'),
        job_type VARCHAR(50),
        category VARCHAR(50),
        experience_level VARCHAR(50),
        education_level VARCHAR(50),
        status ENUM('active', 'filled', 'closed', 'draft') DEFAULT 'active',
        featured BOOLEAN DEFAULT 0,
        views INT DEFAULT 0,
        applications INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        expires_at DATETIME,
        FOREIGN KEY (employer_id) REFERENCES employers(id) ON DELETE CASCADE
    )";
    
    $conn->query($create_jobs_table);
    
    // If no validation errors, proceed with job posting
    if (empty($errors)) {
        $query = "INSERT INTO jobs (
                    employer_id, title, description, responsibilities, requirements, benefits,
                    location, salary_min, salary_max, salary_period, job_type, category,
                    status, featured, expires_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param(
            "isssssddsssssis",
            $_SESSION['user_id'], $title, $description, $responsibilities, $requirements, $benefits,
            $location, $salary_min, $salary_max, $salary_period, $job_type, $category,
            $status, $featured, $expires_at
        );
        
        if ($stmt->execute()) {
            $job_id = $conn->insert_id;
            
            if ($status == 'draft') {
                setMessage("Job has been saved as a draft", "success");
            } else {
                setMessage("Job has been posted successfully", "success");
            }
            
            header("Location: manage-jobs.php");
            exit;
        } else {
            $errors[] = "Failed to post job. Please try again later.";
        }
    }
}

// Prepare data for form
$job_categories = getJobCategories();
$employment_types = getEmploymentTypes();
$experience_levels = getExperienceLevels();
$education_levels = getEducationLevels();

// Include header
include_once '../includes/header.php';
?>

<div class="container mb-5">
    <div class="row">
        <div class="col-lg-10 mx-auto">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h4 class="mb-0"><?php echo $page_header; ?></h4>
                </div>
                <div class="card-body p-4">
                    <?php if(isset($errors) && !empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                        <!-- Job Details Section -->
                        <div class="mb-4">
                            <h5>Basic Information</h5>
                            <div class="card bg-light border-0 mb-3">
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="title" class="form-label">Job Title <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="title" name="title" 
                                               value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>"
                                               required autofocus>
                                        <div class="form-text">Be specific and clear. Use titles that job seekers are searching for.</div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="job_type" class="form-label">Job Type <span class="text-danger">*</span></label>
                                            <select class="form-select" id="job_type" name="job_type" required>
                                                <option value="">Select Job Type</option>
                                                <?php foreach($employment_types as $key => $value): ?>
                                                    <option value="<?php echo $key; ?>" <?php echo (isset($_POST['job_type']) && $_POST['job_type'] == $key) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($value); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="category" class="form-label">Category <span class="text-danger">*</span></label>
                                            <select class="form-select" id="category" name="category" required>
                                                <option value="">Select Category</option>
                                                <?php foreach($job_categories as $key => $value): ?>
                                                    <option value="<?php echo $key; ?>" <?php echo (isset($_POST['category']) && $_POST['category'] == $key) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($value); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="experience_level" class="form-label">Experience Level</label>
                                            <select class="form-select" id="experience_level" name="experience_level">
                                                <option value="">Select Experience Level</option>
                                                <?php foreach($experience_levels as $key => $value): ?>
                                                    <option value="<?php echo $key; ?>" <?php echo (isset($_POST['experience_level']) && $_POST['experience_level'] == $key) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($value); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="education_level" class="form-label">Education Level</label>
                                            <select class="form-select" id="education_level" name="education_level">
                                                <option value="">Select Education Level</option>
                                                <?php foreach($education_levels as $key => $value): ?>
                                                    <option value="<?php echo $key; ?>" <?php echo (isset($_POST['education_level']) && $_POST['education_level'] == $key) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($value); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="location" class="form-label">Location</label>
                                        <input type="text" class="form-control" id="location" name="location" 
                                               value="<?php echo isset($_POST['location']) ? htmlspecialchars($_POST['location']) : ''; ?>">
                                        <div class="form-text">Specify the location for this position. Use "Remote" for remote positions.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Job Description Section -->
                        <div class="mb-4">
                            <h5>Job Description</h5>
                            <div class="card bg-light border-0 mb-3">
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Job Description <span class="text-danger">*</span></label>
                                        <textarea class="form-control" id="description" name="description" rows="6" required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                                        <div class="form-text">Provide a detailed description of the position. What will the successful candidate be doing?</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="responsibilities" class="form-label">Responsibilities</label>
                                        <textarea class="form-control" id="responsibilities" name="responsibilities" rows="4"><?php echo isset($_POST['responsibilities']) ? htmlspecialchars($_POST['responsibilities']) : ''; ?></textarea>
                                        <div class="form-text">List the main responsibilities and duties of the position.</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="requirements" class="form-label">Requirements</label>
                                        <textarea class="form-control" id="requirements" name="requirements" rows="4"><?php echo isset($_POST['requirements']) ? htmlspecialchars($_POST['requirements']) : ''; ?></textarea>
                                        <div class="form-text">List the skills, experience, and qualifications required for this position.</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="benefits" class="form-label">Benefits</label>
                                        <textarea class="form-control" id="benefits" name="benefits" rows="4"><?php echo isset($_POST['benefits']) ? htmlspecialchars($_POST['benefits']) : ''; ?></textarea>
                                        <div class="form-text">Highlight the benefits and perks of working at your company.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Salary Information -->
                        <div class="mb-4">
                            <h5>Salary Information</h5>
                            <div class="card bg-light border-0 mb-3">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label for="salary_min" class="form-label">Minimum Salary</label>
                                            <div class="input-group">
                                                <span class="input-group-text">$</span>
                                                <input type="number" class="form-control" id="salary_min" name="salary_min" min="0" step="0.01"
                                                       value="<?php echo isset($_POST['salary_min']) ? htmlspecialchars($_POST['salary_min']) : ''; ?>">
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-4 mb-3">
                                            <label for="salary_max" class="form-label">Maximum Salary</label>
                                            <div class="input-group">
                                                <span class="input-group-text">$</span>
                                                <input type="number" class="form-control" id="salary_max" name="salary_max" min="0" step="0.01"
                                                       value="<?php echo isset($_POST['salary_max']) ? htmlspecialchars($_POST['salary_max']) : ''; ?>">
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-4 mb-3">
                                            <label for="salary_period" class="form-label">Salary Period</label>
                                            <select class="form-select" id="salary_period" name="salary_period">
                                                <option value="">Select Period</option>
                                                <option value="hourly" <?php echo (isset($_POST['salary_period']) && $_POST['salary_period'] == 'hourly') ? 'selected' : ''; ?>>Hourly</option>
                                                <option value="daily" <?php echo (isset($_POST['salary_period']) && $_POST['salary_period'] == 'daily') ? 'selected' : ''; ?>>Daily</option>
                                                <option value="weekly" <?php echo (isset($_POST['salary_period']) && $_POST['salary_period'] == 'weekly') ? 'selected' : ''; ?>>Weekly</option>
                                                <option value="monthly" <?php echo (isset($_POST['salary_period']) && $_POST['salary_period'] == 'monthly') ? 'selected' : ''; ?>>Monthly</option>
                                                <option value="yearly" <?php echo (isset($_POST['salary_period']) && $_POST['salary_period'] == 'yearly') ? 'selected' : ''; ?>>Yearly</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-text">Providing salary information can increase the number of applications by up to 30%. Leave blank if you prefer not to disclose.</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Posting Options -->
                        <div class="mb-4">
                            <h5>Posting Options</h5>
                            <div class="card bg-light border-0 mb-3">
                                <div class="card-body">
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="featured" name="featured" value="1"
                                               <?php echo (isset($_POST['featured']) && $_POST['featured'] == 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="featured">
                                            Feature this job (Featured jobs appear at the top of search results)
                                        </label>
                                    </div>
                                    
                                    <div class="form-text mb-3">
                                        By default, your job will be active for 30 days from the posting date.
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <button type="submit" name="save_as_draft" class="btn btn-outline-secondary">
                                <i class="fas fa-save me-2"></i> Save as Draft
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-2"></i> Post Job
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';
?>