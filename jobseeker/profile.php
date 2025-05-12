<?php
// Set page title
$page_title = "My Profile";
$page_header = "My Profile";

// Include database connection
require_once '../config/db.php';
// Include functions
require_once '../includes/functions.php';

// Check if user is logged in and is a job seeker
requireJobSeeker();

// Get job seeker information
$query = "SELECT * FROM jobseekers WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$jobseeker = $result->fetch_assoc();

// Process profile update form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $name = sanitizeInput($_POST['name']);
    $headline = sanitizeInput($_POST['headline']);
    $summary = sanitizeInput($_POST['summary']);
    $location = sanitizeInput($_POST['location']);
    $phone = sanitizeInput($_POST['phone']);
    $skills = sanitizeInput($_POST['skills']);
    $experience = sanitizeInput($_POST['experience']);
    $education = sanitizeInput($_POST['education']);
    
    // Validate inputs
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Name is required";
    }
    
    // Handle profile image upload
    $profile_image = $jobseeker['profile_image'];
    
    if (!empty($_FILES['profile_image']['name'])) {
        $upload_dir = '../assets/uploads/profile/';
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        $upload_result = uploadFile($_FILES['profile_image'], $upload_dir, $allowed_types);
        
        if (!$upload_result['status']) {
            $errors[] = $upload_result['message'];
        } else {
            $profile_image = $upload_result['filename'];
        }
    }
    
    // Handle resume upload
    $resume = $jobseeker['resume'];
    
    if (!empty($_FILES['resume']['name'])) {
        $upload_dir = '../assets/uploads/resumes/';
        $allowed_types = ['pdf', 'doc', 'docx'];
        $upload_result = uploadFile($_FILES['resume'], $upload_dir, $allowed_types);
        
        if (!$upload_result['status']) {
            $errors[] = $upload_result['message'];
        } else {
            $resume = $upload_result['filename'];
        }
    }
    
    // If no validation errors, proceed with profile update
    if (empty($errors)) {
        $query = "UPDATE jobseekers SET 
                  name = ?, 
                  headline = ?, 
                  summary = ?, 
                  location = ?, 
                  phone = ?, 
                  skills = ?, 
                  experience = ?, 
                  education = ?, 
                  profile_image = ?,
                  resume = ?
                  WHERE id = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param(
            "ssssssssssi",
            $name, $headline, $summary, $location, $phone, $skills, $experience, $education, $profile_image, $resume, $_SESSION['user_id']
        );
        
        if ($stmt->execute()) {
            setMessage("Profile updated successfully", "success");
            header("Location: profile.php");
            exit;
        } else {
            $errors[] = "Failed to update profile. Please try again later.";
        }
    }
}

// Include header
include_once '../includes/header.php';
?>

<div class="container mb-5">
    <div class="row">
        <div class="col-lg-10 mx-auto">
            <!-- Profile Header -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <div class="d-flex flex-column flex-md-row align-items-center align-items-md-start">
                        <div class="profile-image me-md-4 mb-3 mb-md-0 bg-light rounded-circle d-flex align-items-center justify-content-center" style="width: 120px; height: 120px;">
                            <?php if($jobseeker['profile_image']): ?>
                                <img src="/assets/uploads/profile/<?php echo htmlspecialchars($jobseeker['profile_image']); ?>" alt="<?php echo htmlspecialchars($jobseeker['name']); ?>" class="rounded-circle" style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <i class="fas fa-user text-secondary fa-4x"></i>
                            <?php endif; ?>
                        </div>
                        <div class="text-center text-md-start">
                            <h2 class="mb-1"><?php echo htmlspecialchars($jobseeker['name']); ?></h2>
                            <p class="lead mb-2"><?php echo $jobseeker['headline'] ? htmlspecialchars($jobseeker['headline']) : 'Add your professional headline'; ?></p>
                            
                            <div class="mb-3">
                                <span class="text-muted me-3">
                                    <i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($jobseeker['email']); ?>
                                </span>
                                <?php if($jobseeker['phone']): ?>
                                    <span class="text-muted me-3">
                                        <i class="fas fa-phone me-1"></i> <?php echo htmlspecialchars($jobseeker['phone']); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if($jobseeker['location']): ?>
                                    <span class="text-muted">
                                        <i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($jobseeker['location']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                                <i class="fas fa-edit me-2"></i> Edit Profile
                            </button>
                            
                            <?php if($jobseeker['resume']): ?>
                                <a href="/assets/uploads/resumes/<?php echo htmlspecialchars($jobseeker['resume']); ?>" target="_blank" class="btn btn-outline-primary ms-2">
                                    <i class="fas fa-file-pdf me-2"></i> View Resume
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if(isset($errors) && !empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <!-- Main Profile Content -->
                <div class="col-lg-8">
                    <!-- About Me -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">About Me</h5>
                        </div>
                        <div class="card-body">
                            <?php if($jobseeker['summary']): ?>
                                <p><?php echo nl2br(htmlspecialchars($jobseeker['summary'])); ?></p>
                            <?php else: ?>
                                <p class="text-muted">Add a summary to tell employers about yourself and your career goals.</p>
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                                    <i class="fas fa-plus-circle me-2"></i> Add Summary
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Experience -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Work Experience</h5>
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                        <div class="card-body">
                            <?php if($jobseeker['experience']): ?>
                                <?php echo nl2br(htmlspecialchars($jobseeker['experience'])); ?>
                            <?php else: ?>
                                <p class="text-muted">Add your work experience to showcase your professional history.</p>
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                                    <i class="fas fa-plus-circle me-2"></i> Add Experience
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Education -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Education</h5>
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                        <div class="card-body">
                            <?php if($jobseeker['education']): ?>
                                <?php echo nl2br(htmlspecialchars($jobseeker['education'])); ?>
                            <?php else: ?>
                                <p class="text-muted">Add your educational background to highlight your qualifications.</p>
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                                    <i class="fas fa-plus-circle me-2"></i> Add Education
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Sidebar -->
                <div class="col-lg-4">
                    <!-- Skills -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Skills</h5>
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                        <div class="card-body">
                            <?php if($jobseeker['skills']): ?>
                                <div class="d-flex flex-wrap">
                                    <?php 
                                    $skills = explode(',', $jobseeker['skills']);
                                    foreach($skills as $skill): 
                                        $skill = trim($skill);
                                        if(!empty($skill)):
                                    ?>
                                        <span class="badge bg-primary me-2 mb-2 p-2"><?php echo htmlspecialchars($skill); ?></span>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">Add your skills to help employers find you.</p>
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                                    <i class="fas fa-plus-circle me-2"></i> Add Skills
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Resume -->
                    <div class="card border-0 shadow-sm mb-4" id="resume-section">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Resume</h5>
                        </div>
                        <div class="card-body">
                            <?php if($jobseeker['resume']): ?>
                                <div class="d-flex align-items-center mb-3">
                                    <i class="far fa-file-pdf fa-2x text-danger me-3"></i>
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($jobseeker['resume']); ?></div>
                                        <div class="text-muted small">Uploaded: <?php echo formatDate($jobseeker['updated_at']); ?></div>
                                    </div>
                                </div>
                                <div class="d-flex">
                                    <a href="/assets/uploads/resumes/<?php echo htmlspecialchars($jobseeker['resume']); ?>" target="_blank" class="btn btn-sm btn-outline-primary me-2">
                                        <i class="fas fa-eye me-1"></i> View
                                    </a>
                                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                                        <i class="fas fa-sync-alt me-1"></i> Update
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-3">
                                    <i class="fas fa-file-upload fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No resume uploaded yet. Upload your resume to apply for jobs quickly.</p>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                                        <i class="fas fa-upload me-2"></i> Upload Resume
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Profile Completeness -->
                    <?php
                    $profile_fields = [
                        'headline' => 'Professional headline',
                        'summary' => 'Summary',
                        'location' => 'Location',
                        'phone' => 'Phone number',
                        'resume' => 'Resume',
                        'skills' => 'Skills',
                        'experience' => 'Work experience',
                        'education' => 'Education',
                        'profile_image' => 'Profile picture'
                    ];
                    
                    $missing_fields = [];
                    foreach ($profile_fields as $field => $label) {
                        if (empty($jobseeker[$field])) {
                            $missing_fields[] = $label;
                        }
                    }
                    
                    $completion_percentage = 100 - (count($missing_fields) / count($profile_fields) * 100);
                    $completion_percentage = round($completion_percentage);
                    ?>
                    
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Profile Completeness</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span><?php echo $completion_percentage; ?>% Complete</span>
                                <span class="text-<?php echo $completion_percentage == 100 ? 'success' : ($completion_percentage >= 70 ? 'warning' : 'danger'); ?>">
                                    <?php echo $completion_percentage == 100 ? 'All Set!' : ($completion_percentage >= 70 ? 'Almost There' : 'Needs Work'); ?>
                                </span>
                            </div>
                            
                            <div class="progress mb-4" style="height: 8px;">
                                <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo $completion_percentage; ?>%" aria-valuenow="<?php echo $completion_percentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            
                            <?php if (!empty($missing_fields)): ?>
                                <h6 class="text-muted mb-2">Missing Information:</h6>
                                <ul class="list-unstyled">
                                    <?php foreach ($missing_fields as $field): ?>
                                        <li class="mb-1">
                                            <i class="fas fa-exclamation-circle text-warning me-2"></i>
                                            <?php echo htmlspecialchars($field); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                
                                <button class="btn btn-primary w-100 mt-2" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                                    <i class="fas fa-edit me-2"></i> Complete Profile
                                </button>
                            <?php else: ?>
                                <div class="alert alert-success mb-0">
                                    <i class="fas fa-check-circle me-2"></i>
                                    Your profile is complete! A complete profile increases your chances of getting noticed by employers.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Profile Modal -->
<div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editProfileModalLabel">Edit Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo htmlspecialchars($jobseeker['name']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="headline" class="form-label">Professional Headline</label>
                                <input type="text" class="form-control" id="headline" name="headline" 
                                       value="<?php echo htmlspecialchars($jobseeker['headline']); ?>"
                                       placeholder="e.g., Senior Software Engineer | Java Developer | Cloud Architect">
                                <div class="form-text">A brief professional title that appears under your name</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="location" class="form-label">Location</label>
                                <input type="text" class="form-control" id="location" name="location" 
                                       value="<?php echo htmlspecialchars($jobseeker['location']); ?>"
                                       placeholder="e.g., New York, NY">
                            </div>
                            
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($jobseeker['phone']); ?>"
                                       placeholder="e.g., (123) 456-7890">
                            </div>
                            
                            <div class="mb-3">
                                <label for="skills" class="form-label">Skills</label>
                                <textarea class="form-control" id="skills" name="skills" rows="3" placeholder="e.g., JavaScript, React, Node.js, Project Management, etc. (comma-separated)"><?php echo htmlspecialchars($jobseeker['skills']); ?></textarea>
                                <div class="form-text">Enter your skills separated by commas</div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="profile_image" class="form-label">Profile Image</label>
                                <input type="file" class="form-control" id="profile_image" name="profile_image" accept="image/*">
                                <div class="form-text">Upload a professional photo (JPG, PNG, or GIF)</div>
                                
                                <?php if($jobseeker['profile_image']): ?>
                                    <div class="mt-2">
                                        <img src="/assets/uploads/profile/<?php echo htmlspecialchars($jobseeker['profile_image']); ?>" alt="Current profile image" class="rounded" style="max-width: 100px; max-height: 100px;">
                                        <span class="ms-2">Current image</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-3">
                                <label for="resume" class="form-label">Resume</label>
                                <input type="file" class="form-control" id="resume" name="resume" accept=".pdf,.doc,.docx">
                                <div class="form-text">Upload your resume (PDF, DOC, or DOCX)</div>
                                
                                <?php if($jobseeker['resume']): ?>
                                    <div class="mt-2 d-flex align-items-center">
                                        <i class="far fa-file-pdf fa-2x text-danger me-2"></i>
                                        <span><?php echo htmlspecialchars($jobseeker['resume']); ?> (Current resume)</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <div class="mb-3">
                                <label for="summary" class="form-label">Professional Summary</label>
                                <textarea class="form-control" id="summary" name="summary" rows="4" placeholder="Brief overview of your professional background, skills, and career goals"><?php echo htmlspecialchars($jobseeker['summary']); ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="experience" class="form-label">Work Experience</label>
                                <textarea class="form-control" id="experience" name="experience" rows="6" placeholder="List your work experience, including job titles, companies, dates, and responsibilities"><?php echo htmlspecialchars($jobseeker['experience']); ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="education" class="form-label">Education</label>
                                <textarea class="form-control" id="education" name="education" rows="4" placeholder="List your educational background, including degrees, institutions, and dates"><?php echo htmlspecialchars($jobseeker['education']); ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="button" class="btn btn-secondary me-md-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';
?>