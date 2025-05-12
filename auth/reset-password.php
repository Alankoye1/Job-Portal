<?php
// Set page title
$page_title = "Reset Password";
$page_header = "Reset Your Password";

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
        header("Location: /employer/dashboard.php");
    } else {
        header("Location: /jobseeker/dashboard.php");
    }
    exit;
}

$step = 'request'; // Default step: request password reset

// Check if reset token is in URL
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = sanitizeInput($_GET['token']);
    $email = isset($_GET['email']) ? sanitizeInput($_GET['email']) : '';
    
    // Verify token
    $query = "SELECT * FROM password_resets WHERE token = ? AND email = ? AND expires_at > NOW()";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $token, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $step = 'reset'; // Show password reset form
    } else {
        setMessage("Invalid or expired password reset link. Please request a new one.", "danger");
        header("Location: reset-password.php");
        exit;
    }
}

// Process password reset request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['request'])) {
    $email = sanitizeInput($_POST['email']);
    
    // Validate email
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($errors)) {
        // Check if user exists
        $query = "SELECT 1 FROM employers WHERE email = ? UNION SELECT 1 FROM jobseekers WHERE email = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $email, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Create password_resets table if it doesn't exist
            $create_table_query = "CREATE TABLE IF NOT EXISTS password_resets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL,
                token VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NOT NULL
            )";
            $conn->query($create_table_query);
            
            // Generate token and expiration time (1 hour from now)
            $token = generateToken();
            $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Delete any existing tokens for this email
            $query = "DELETE FROM password_resets WHERE email = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $email);
            $stmt->execute();
            
            // Insert new token
            $query = "INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sss", $email, $token, $expires_at);
            $stmt->execute();
            
            // In a real application, you would send an email with the reset link
            // For demonstration purposes, we'll just show the link on the page
            
            $reset_url = "http://{$_SERVER['HTTP_HOST']}/auth/reset-password.php?token=$token&email=$email";
            $reset_link = "<a href='$reset_url'>$reset_url</a>";
            
            setMessage("Password reset link has been sent to your email. Check your inbox.", "success");
            
            // Display reset link (for demonstration purposes only)
            $_SESSION['demo_reset_link'] = $reset_link;
            $step = 'link_sent';
        } else {
            // Don't reveal that the user doesn't exist for security reasons
            setMessage("If your email address exists in our database, you will receive a password reset link shortly.", "info");
            $step = 'link_sent';
        }
    }
}

// Process password reset form
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reset'])) {
    $token = sanitizeInput($_POST['token']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate inputs
    $errors = [];
    
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    if (empty($errors)) {
        // Verify token again
        $query = "SELECT * FROM password_resets WHERE token = ? AND email = ? AND expires_at > NOW()";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $token, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            // Hash new password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Update password in both tables
            $tables = ['employers', 'jobseekers'];
            $password_updated = false;
            
            foreach ($tables as $table) {
                $query = "UPDATE $table SET password = ? WHERE email = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ss", $hashed_password, $email);
                $stmt->execute();
                
                if ($stmt->affected_rows > 0) {
                    $password_updated = true;
                }
            }
            
            if ($password_updated) {
                // Delete used token
                $query = "DELETE FROM password_resets WHERE token = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("s", $token);
                $stmt->execute();
                
                setMessage("Your password has been successfully reset. You can now log in with your new password.", "success");
                header("Location: login.php");
                exit;
            } else {
                $errors[] = "Failed to update password. Please try again.";
            }
        } else {
            $errors[] = "Invalid or expired token. Please request a new password reset link.";
            $step = 'request';
        }
    }
}

// Include header
include_once '../includes/header.php';
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <i class="fas fa-key fa-4x text-primary mb-3"></i>
                        <?php if ($step === 'request'): ?>
                            <h2 class="card-title">Reset Your Password</h2>
                            <p class="text-muted">Enter your email to receive a password reset link</p>
                        <?php elseif ($step === 'link_sent'): ?>
                            <h2 class="card-title">Check Your Email</h2>
                            <p class="text-muted">A password reset link has been sent to your email</p>
                        <?php elseif ($step === 'reset'): ?>
                            <h2 class="card-title">Create New Password</h2>
                            <p class="text-muted">Please enter your new password</p>
                        <?php endif; ?>
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
                    
                    <?php if($step === 'request'): ?>
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                           required autofocus>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" name="request" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-2"></i> Send Reset Link
                                </button>
                            </div>
                        </form>
                    <?php elseif($step === 'link_sent'): ?>
                        <div class="alert alert-info">
                            <p>A password reset link has been sent to your email address. Please check your inbox and follow the instructions to reset your password.</p>
                            <p class="mb-0">If you don't receive the email within a few minutes, please check your spam folder.</p>
                        </div>
                        
                        <?php if(isset($_SESSION['demo_reset_link'])): ?>
                            <div class="alert alert-warning">
                                <p class="mb-0"><strong>Demo Only:</strong> In a production environment, this link would be sent via email. For demonstration, you can click the link below:</p>
                                <p class="mb-0 mt-2"><?php echo $_SESSION['demo_reset_link']; ?></p>
                            </div>
                            <?php unset($_SESSION['demo_reset_link']); ?>
                        <?php endif; ?>
                        
                        <div class="d-grid gap-2 mt-4">
                            <a href="login.php" class="btn btn-outline-primary">
                                <i class="fas fa-arrow-left me-2"></i> Return to Login
                            </a>
                        </div>
                    <?php elseif($step === 'reset'): ?>
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                            <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">New Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" required autofocus>
                                </div>
                                <div class="mt-2">
                                    <div class="progress" style="height: 5px;">
                                        <div id="password-strength" class="progress-bar" role="progressbar" style="width: 0%"></div>
                                    </div>
                                    <small class="form-text text-muted">Use at least 8 characters with letters, numbers and symbols</small>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" name="reset" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i> Reset Password
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                    
                    <hr class="my-4">
                    
                    <div class="text-center">
                        <p class="mb-0">
                            <?php if($step !== 'request'): ?>
                                <a href="reset-password.php" class="text-decoration-none">Request a new link</a> | 
                            <?php endif; ?>
                            <a href="login.php" class="text-decoration-none">Back to login</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';
?>