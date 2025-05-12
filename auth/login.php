<?php
// Set page title
$page_title = "Login";
$page_header = "Welcome Back";

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

// Process login form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']) ? true : false;

    // Validate inputs
    $errors = [];

    if (empty($email)) {
        $errors[] = "Email is required";
    }

    if (empty($password)) {
        $errors[] = "Password is required";
    }

    // If no validation errors, proceed with login
    if (empty($errors)) {
        // Verify user credentials - checking both user tables
        $query = "SELECT u.*, 'employer' as user_type FROM employers u WHERE u.email = ? 
                 UNION 
                 SELECT u.*, 'jobseeker' as user_type FROM jobseekers u WHERE u.email = ?";

        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $email, $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();

            // Verify password
            if (password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_type'] = $user['user_type'];

                // Set remember me cookie if checked
                if ($remember) {
                    $token = generateToken();
                    $expires = time() + (86400 * 30); // 30 days

                    // Store token in database
                    $table = $user['user_type'] == 'employer' ? 'employers' : 'jobseekers';
                    $query = "UPDATE $table SET remember_token = ?, token_expires = FROM_UNIXTIME(?) WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("sii", $token, $expires, $user['id']);
                    $stmt->execute();

                    // Set cookie
                    setcookie('remember_token', $token, $expires, '/');
                    setcookie('user_email', $email, $expires, '/');
                }

                setMessage("Login successful. Welcome back!", "success");

                // Redirect to appropriate dashboard
                if ($user['user_type'] == 'employer') {
                    header("Location: ../employer/dashboard.php");
                } else {
                    header("Location: ../jobseeker/dashboard.php");
                }
                exit;
            } else {
                $errors[] = "Invalid email or password";
            }
        } else {
            $errors[] = "Invalid email or password";
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
                        <i class="fas fa-user-circle fa-4x text-primary mb-3"></i>
                        <h2 class="card-title">Login to Your Account</h2>
                        <p class="text-muted">Enter your credentials to access your account</p>
                    </div>

                    <?php if (isset($errors) && !empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

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

                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="remember" name="remember">
                                <label class="form-check-label" for="remember">Remember me</label>
                            </div>
                            <a href="reset-password.php" class="text-decoration-none">Forgot password?</a>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-sign-in-alt me-2"></i> Login
                            </button>
                        </div>
                    </form>

                    <hr class="my-4">

                    <div class="text-center">
                        <p class="mb-0">Don't have an account? <a href="register.php" class="text-decoration-none">Register now</a></p>
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