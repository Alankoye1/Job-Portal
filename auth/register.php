<?php
// Set page title
$page_title = "Register";
$page_header = "Create an Account";

// Include database connection
require_once '../config/db.php';
// Include functions
require_once '../includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is already logged in
if (isLoggedIn()) {
    // Redirect to appropriate dashboard
    if (isEmployer()) {
        header("Location: ../employer/dashboard.php");
    } else {
        header("Location: ../jobseeker/dashboard.php");
    }
    exit;
}

// Process registration form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = sanitizeInput($_POST['name']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $user_type = sanitizeInput($_POST['user_type']);
    
    // Validate inputs
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Name is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    if ($user_type !== 'employer' && $user_type !== 'jobseeker') {
        $errors[] = "Invalid account type";
    }
    
    // Check if email already exists in either employer or jobseeker table
    $query = "SELECT 1 FROM employers WHERE email = ? UNION SELECT 1 FROM jobseekers WHERE email = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $email, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $errors[] = "Email already in use. Please use a different email or login to your existing account.";
    }
    
    // If no validation errors, proceed with registration
    if (empty($errors)) {
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert user data into appropriate table
        if ($user_type == 'employer') {
            // Insert employer data
            $query = "INSERT INTO employers (company_name, email, password) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sss", $name, $email, $hashed_password);
        } else {
            // Split name into first_name and last_name
            $name_parts = explode(' ', $name, 2);
            $first_name = $name_parts[0];
            $last_name = isset($name_parts[1]) ? $name_parts[1] : '';
            
            // Insert jobseeker data
            $query = "INSERT INTO jobseekers (first_name, last_name, email, password) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssss", $first_name, $last_name, $email, $hashed_password);
        }
        
        if ($stmt->execute()) {
            $user_id = $conn->insert_id;
            
            // Set session variables
            $_SESSION['user_id'] = $user_id;
            if ($user_type == 'employer') {
                $_SESSION['user_name'] = $name; // Company name
            } else {
                $_SESSION['user_name'] = $name; // Full name for jobseekers
            }
            $_SESSION['user_email'] = $email;
            $_SESSION['user_type'] = $user_type;
            
            setMessage("Registration successful! Welcome to JobConnect.", "success");
            
            // Redirect to appropriate dashboard
            if ($user_type == 'employer') {
                header("Location: ../employer/dashboard.php");
            } else {
                header("Location: ../jobseeker/dashboard.php");
            }
            exit;
        } else {
            $errors[] = "Registration failed. Please try again later.";
        }
    }
}

// Include header
include_once '../includes/header.php';
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <i class="fas fa-user-plus fa-4x text-primary mb-3"></i>
                        <h2 class="card-title">Create a New Account</h2>
                        <p class="text-muted">Join JobConnect and start your career journey</p>
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
                    
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                        <div class="row mb-3">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <label for="name" class="form-label">Full Name</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                                           required autofocus>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                           required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                                <div class="mt-2">
                                    <div class="progress" style="height: 5px;">
                                        <div id="password-strength" class="progress-bar" role="progressbar" style="width: 0%"></div>
                                    </div>
                                    <small class="form-text text-muted">Use at least 8 characters with letters, numbers and symbols</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label">Account Type</label>
                            <div class="row">
                                <div class="col-md-6 mb-3 mb-md-0">
                                    <div class="card border-primary h-100">
                                        <div class="card-body text-center">
                                            <input type="radio" class="btn-check" name="user_type" id="employer" value="employer" 
                                                   <?php echo (isset($_POST['user_type']) && $_POST['user_type'] == 'employer') ? 'checked' : ''; ?> required>
                                            <label class="btn btn-outline-primary w-100 h-100 d-flex flex-column align-items-center justify-content-center" for="employer">
                                                <i class="fas fa-building fa-3x mb-3"></i>
                                                <h5 class="mb-0">Employer</h5>
                                                <p class="text-muted mb-0">Post jobs & find talent</p>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card border-primary h-100">
                                        <div class="card-body text-center">
                                            <input type="radio" class="btn-check" name="user_type" id="jobseeker" value="jobseeker" 
                                                   <?php echo (isset($_POST['user_type']) && $_POST['user_type'] == 'jobseeker') ? 'checked' : ''; ?> required>
                                            <label class="btn btn-outline-primary w-100 h-100 d-flex flex-column align-items-center justify-content-center" for="jobseeker">
                                                <i class="fas fa-user-tie fa-3x mb-3"></i>
                                                <h5 class="mb-0">Job Seeker</h5>
                                                <p class="text-muted mb-0">Find jobs & build career</p>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="terms" required>
                            <label class="form-check-label" for="terms">
                                I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms of Service</a> and <a href="#" data-bs-toggle="modal" data-bs-target="#privacyModal">Privacy Policy</a>
                            </label>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-user-plus me-2"></i> Create Account
                            </button>
                        </div>
                    </form>
                    
                    <hr class="my-4">
                    
                    <div class="text-center">
                        <p class="mb-0">Already have an account? <a href="login.php" class="text-decoration-none">Login</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Terms of Service Modal -->
<div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="termsModalLabel">Terms of Service</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h5>1. Acceptance of Terms</h5>
                <p>By accessing or using the JobConnect service, you agree to be bound by these Terms of Service.</p>
                
                <h5>2. User Account</h5>
                <p>You are responsible for maintaining the security of your account and password. The company cannot and will not be liable for any loss or damage from your failure to comply with this security obligation.</p>
                
                <h5>3. Prohibited Activities</h5>
                <p>You agree not to engage in any of the following prohibited activities:</p>
                <ul>
                    <li>Copying, distributing, or disclosing any part of the service in any medium</li>
                    <li>Using any automated system to access the service</li>
                    <li>Attempting to interfere with the proper working of the service</li>
                </ul>
                
                <h5>4. Content Rights</h5>
                <p>The service and its original content, features, and functionality are owned by JobConnect and are protected by international copyright, trademark, patent, trade secret, and other intellectual property laws.</p>
                
                <h5>5. Termination</h5>
                <p>We may terminate or suspend your account and bar access to the service immediately, without prior notice or liability, under our sole discretion, for any reason whatsoever and without limitation.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">I Understand</button>
            </div>
        </div>
    </div>
</div>

<!-- Privacy Policy Modal -->
<div class="modal fade" id="privacyModal" tabindex="-1" aria-labelledby="privacyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="privacyModalLabel">Privacy Policy</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h5>1. Information We Collect</h5>
                <p>We collect information you provide directly to us when you create an account, update your profile, post a job, apply for a job, or communicate with us.</p>
                
                <h5>2. How We Use Information</h5>
                <p>We use the information we collect to:</p>
                <ul>
                    <li>Provide, maintain, and improve our services</li>
                    <li>Send technical notices, updates, and administrative messages</li>
                    <li>Respond to your comments, questions, and requests</li>
                    <li>Monitor and analyze trends, usage, and activities in connection with our services</li>
                </ul>
                
                <h5>3. Information Sharing</h5>
                <p>We may share information as follows:</p>
                <ul>
                    <li>With employers when you apply for their job postings</li>
                    <li>With service providers who perform services on our behalf</li>
                    <li>If we believe disclosure is necessary to comply with any applicable law or legal process</li>
                </ul>
                
                <h5>4. Security</h5>
                <p>We take reasonable measures to help protect information about you from loss, theft, misuse and unauthorized access, disclosure, alteration and destruction.</p>
                
                <h5>5. Changes to this Policy</h5>
                <p>We may change this Privacy Policy from time to time. If we make changes, we will notify you by revising the date at the top of the policy.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">I Understand</button>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';
?>