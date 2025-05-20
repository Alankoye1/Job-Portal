<?php
// Set page title
$page_title = "Employer Dashboard";
$page_header = "Dashboard";

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

// Get job statistics
$jobs_query = "SELECT 
              (SELECT COUNT(*) FROM jobs WHERE employer_id = ? AND status = 'active') as active_jobs,
              (SELECT COUNT(*) FROM jobs WHERE employer_id = ? AND status = 'filled') as filled_jobs,
              (SELECT COUNT(*) FROM jobs WHERE employer_id = ? AND status = 'closed') as closed_jobs,
              (SELECT COUNT(*) FROM jobs WHERE employer_id = ? AND status = 'draft') as draft_jobs,
              (SELECT SUM(views) FROM jobs WHERE employer_id = ?) as total_views,
              (SELECT COUNT(*) FROM applications a JOIN jobs j ON a.job_id = j.id WHERE j.employer_id = ?) as total_applications";
$stmt = $conn->prepare($jobs_query);
$stmt->bind_param("iiiiii", $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']);
$stmt->execute();
$stats_result = $stmt->get_result();
$stats = $stats_result->fetch_assoc();

// Get recent job applications
$applications_query = "SELECT a.*, j.title as job_title, 
                     CONCAT(js.first_name, ' ', js.last_name) as applicant_name, 
                     js.email as applicant_email
                     FROM applications a
                     JOIN jobs j ON a.job_id = j.id
                     JOIN jobseekers js ON a.jobseeker_id = js.id
                     WHERE j.employer_id = ?
                     ORDER BY a.created_at DESC
                     LIMIT 5";
$stmt = $conn->prepare($applications_query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$applications_result = $stmt->get_result();

// Get recent job postings
$recent_jobs_query = "SELECT j.*, 
                     (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id) as applications 
                     FROM jobs j
                     WHERE j.employer_id = ? 
                     ORDER BY j.created_at DESC 
                     LIMIT 5";
$stmt = $conn->prepare($recent_jobs_query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$recent_jobs_result = $stmt->get_result();

// Include header
include_once '../includes/header.php';
?>

<div class="container mb-5">
    <div class="row mb-4">
        <div class="col-md-6">
            <h1 class="mb-0">Welcome back, <?php echo htmlspecialchars($employer['company_name']); ?>!</h1>
            <p class="text-muted">Here's what's happening with your job postings</p>
        </div>
        <div class="col-md-6 text-md-end">
            <a href="post-job.php" class="btn btn-primary">
                <i class="fas fa-plus-circle me-2"></i> Post a New Job
            </a>
        </div>
    </div>
    
    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3 col-sm-6 mb-4 mb-md-0">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="stat-icon bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                            <i class="fas fa-briefcase fa-fw"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-0">Active Jobs</h6>
                            <h3 class="mb-0"><?php echo $stats['active_jobs'] ?? 0; ?></h3>
                        </div>
                    </div>
                    <a href="manage-jobs.php?status=active" class="text-decoration-none small">
                        View all active jobs <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6 mb-4 mb-md-0">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="stat-icon bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                            <i class="fas fa-file-alt fa-fw"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-0">Applications</h6>
                            <h3 class="mb-0"><?php echo $stats['total_applications'] ?? 0; ?></h3>
                        </div>
                    </div>
                    <a href="applications.php" class="text-decoration-none small">
                        View all applications <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6 mb-4 mb-md-0">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="stat-icon bg-info text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                            <i class="fas fa-eye fa-fw"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-0">Job Views</h6>
                            <h3 class="mb-0"><?php echo $stats['total_views'] ?? 0; ?></h3>
                        </div>
                    </div>
                    <a href="manage-jobs.php" class="text-decoration-none small">
                        View job performance <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="stat-icon bg-warning text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                            <i class="fas fa-file-invoice fa-fw"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-0">Filled Jobs</h6>
                            <h3 class="mb-0"><?php echo $stats['filled_jobs'] ?? 0; ?></h3>
                        </div>
                    </div>
                    <a href="manage-jobs.php?status=filled" class="text-decoration-none small">
                        View filled positions <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Recent Applications -->
        <div class="col-lg-7 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Recent Applications</h5>
                        <a href="applications.php" class="btn btn-sm btn-outline-primary">
                            View All <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if($applications_result && $applications_result->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col">Applicant</th>
                                        <th scope="col">Job</th>
                                        <th scope="col">Date</th>
                                        <th scope="col">Status</th>
                                        <th scope="col">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($application = $applications_result->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar bg-light rounded-circle text-center me-2" style="width: 32px; height: 32px; line-height: 32px;">
                                                        <i class="fas fa-user text-primary"></i>
                                                    </div>
                                                    <div>
                                                        <?php echo htmlspecialchars($application['applicant_name']); ?>
                                                        <div class="small text-muted"><?php echo htmlspecialchars($application['applicant_email']); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($application['job_title']); ?></td>
                                            <td><?php echo formatDate($application['created_at']); ?></td>
                                            <td>
                                                <?php 
                                                $status_badges = [
                                                    'pending' => 'bg-warning text-dark',
                                                    'reviewed' => 'bg-info text-dark',
                                                    'interviewed' => 'bg-primary',
                                                    'offered' => 'bg-success',
                                                    'hired' => 'bg-success',
                                                    'rejected' => 'bg-danger'
                                                ];
                                                $badge_class = isset($status_badges[$application['status']]) ? $status_badges[$application['status']] : 'bg-secondary';
                                                ?>
                                                <span class="badge <?php echo $badge_class; ?>">
                                                    <?php echo ucfirst($application['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="view-application.php?id=<?php echo $application['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="p-4 text-center">
                            <p class="text-muted mb-0">No applications received yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Recent Jobs -->
        <div class="col-lg-5 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Recent Jobs</h5>
                        <a href="manage-jobs.php" class="btn btn-sm btn-outline-primary">
                            View All <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if($recent_jobs_result && $recent_jobs_result->num_rows > 0): ?>
                        <div class="list-group list-group-flush">
                            <?php while($job = $recent_jobs_result->fetch_assoc()): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($job['title']); ?></h6>
                                            <p class="mb-1 small text-muted">
                                                <i class="fas fa-map-marker-alt me-1"></i> <?php echo $job['location'] ? htmlspecialchars($job['location']) : 'Remote'; ?>
                                                <?php if($job['job_type']): ?>
                                                    <span class="mx-1">•</span>
                                                    <i class="fas fa-briefcase me-1"></i> <?php echo htmlspecialchars(isset(getEmploymentTypes()[$job['job_type']]) ? getEmploymentTypes()[$job['job_type']] : $job['job_type']); ?>
                                                <?php endif; ?>
                                            </p>
                                            <div>
                                                <?php 
                                                $status_badges = [
                                                    'active' => 'bg-success',
                                                    'filled' => 'bg-primary',
                                                    'closed' => 'bg-secondary',
                                                    'draft' => 'bg-warning text-dark'
                                                ];
                                                $badge_class = isset($status_badges[$job['status']]) ? $status_badges[$job['status']] : 'bg-secondary';
                                                ?>
                                                <span class="badge <?php echo $badge_class; ?> me-1">
                                                    <?php echo ucfirst($job['status']); ?>
                                                </span>
                                                
                                                <?php if($job['applications'] > 0): ?>
                                                    <span class="badge bg-info text-dark">
                                                        <?php echo $job['applications']; ?> application<?php echo $job['applications'] != 1 ? 's' : ''; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-link text-decoration-none" type="button" id="jobActions<?php echo $job['id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="jobActions<?php echo $job['id']; ?>">
                                                <li><a class="dropdown-item" href="edit-job.php?id=<?php echo $job['id']; ?>"><i class="fas fa-edit me-2"></i> Edit</a></li>
                                                <li><a class="dropdown-item" href="../job-details.php?id=<?php echo $job['id']; ?>"><i class="fas fa-eye me-2"></i> View</a></li>
                                                <li><a class="dropdown-item" href="applications.php?job_id=<?php echo $job['id']; ?>"><i class="fas fa-users me-2"></i> Applications</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <?php if($job['status'] == 'active'): ?>
                                                    <li><a class="dropdown-item text-warning" href="mark-job.php?id=<?php echo $job['id']; ?>&status=filled"><i class="fas fa-check-circle me-2"></i> Mark as Filled</a></li>
                                                    <li><a class="dropdown-item text-danger" href="mark-job.php?id=<?php echo $job['id']; ?>&status=closed"><i class="fas fa-times-circle me-2"></i> Close Job</a></li>
                                                <?php elseif($job['status'] == 'draft'): ?>
                                                    <li><a class="dropdown-item text-success" href="publish-job.php?id=<?php echo $job['id']; ?>"><i class="fas fa-paper-plane me-2"></i> Publish</a></li>
                                                <?php elseif($job['status'] == 'closed' || $job['status'] == 'filled'): ?>
                                                    <li><a class="dropdown-item text-success" href="mark-job.php?id=<?php echo $job['id']; ?>&status=active"><i class="fas fa-redo me-2"></i> Reactivate</a></li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="p-4 text-center">
                            <p class="text-muted mb-0">No jobs posted yet. <a href="post-job.php">Post your first job</a>.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Company Info -->
        <div class="col-lg-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Company Information</h5>
                        <a href="edit-job.php" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-edit me-1"></i> Edit
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center mb-4">
                        <div class="company-logo me-3 bg-light rounded p-3" style="width: 80px; height: 80px;">
                            <?php if($employer['logo']): ?>
                                <img src="/assets/uploads/logos/<?php echo htmlspecialchars($employer['logo']); ?>" alt="<?php echo htmlspecialchars($employer['company_name']); ?>" class="img-fluid">
                            <?php else: ?>
                                <div class="d-flex align-items-center justify-content-center h-100 bg-light rounded">
                                    <i class="fas fa-building text-secondary fa-2x"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h5 class="mb-1"><?php echo $employer['company_name'] ? htmlspecialchars($employer['company_name']) : 'Your Company'; ?></h5>
                            <p class="text-muted mb-0">Account Owner</p>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <div class="card bg-light border-0">
                                <div class="card-body">
                                    <h6 class="card-title text-muted">Contact Information</h6>
                                    <ul class="list-unstyled mb-0">
                                        <li class="mb-2">
                                            <i class="fas fa-envelope text-primary me-2"></i>
                                            <?php echo htmlspecialchars($employer['email']); ?>
                                        </li>
                                        <?php if($employer['phone']): ?>
                                            <li class="mb-2">
                                                <i class="fas fa-phone text-primary me-2"></i>
                                                <?php echo htmlspecialchars($employer['phone']); ?>
                                            </li>
                                        <?php endif; ?>
                                        <?php if($employer['website']): ?>
                                            <li>
                                                <i class="fas fa-globe text-primary me-2"></i>
                                                <a href="<?php echo htmlspecialchars($employer['website']); ?>" target="_blank">
                                                    <?php echo htmlspecialchars($employer['website']); ?>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card bg-light border-0">
                                <div class="card-body">
                                    <h6 class="card-title text-muted">Location</h6>
                                    <p class="mb-0">
                                        <i class="fas fa-map-marker-alt text-primary me-2"></i>
                                        <?php echo $employer['location'] ? htmlspecialchars($employer['location']) : 'Not specified'; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if($employer['description']): ?>
                        <h6>Company Description</h6>
                        <p class="text-muted">
                            <?php echo nl2br(htmlspecialchars($employer['description'])); ?>
                        </p>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Your company profile is incomplete. Adding your company description, logo, and other details can help attract more candidates.
                            <a href="profile.php" class="alert-link">Complete your profile now</a>.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Quick Links & Insights -->
        <div class="col-lg-6">
            <div class="row">
                <div class="col-12 mb-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Quick Links</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <a href="post-job.php" class="card text-decoration-none border-0 bg-light h-100">
                                        <div class="card-body d-flex align-items-center">
                                            <i class="fas fa-plus-circle fa-2x text-primary me-3"></i>
                                            <div>
                                                <h6 class="mb-0 text-dark">Post a New Job</h6>
                                                <p class="text-muted small mb-0">Create a new job listing</p>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-md-6">
                                    <a href="applications.php" class="card text-decoration-none border-0 bg-light h-100">
                                        <div class="card-body d-flex align-items-center">
                                            <i class="fas fa-users fa-2x text-primary me-3"></i>
                                            <div>
                                                <h6 class="mb-0 text-dark">View Applications</h6>
                                                <p class="text-muted small mb-0">Review candidate profiles</p>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-md-6">
                                    <a href="manage-jobs.php" class="card text-decoration-none border-0 bg-light h-100">
                                        <div class="card-body d-flex align-items-center">
                                            <i class="fas fa-briefcase fa-2x text-primary me-3"></i>
                                            <div>
                                                <h6 class="mb-0 text-dark">Manage Jobs</h6>
                                                <p class="text-muted small mb-0">Edit or update job posts</p>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-md-6">
                                    <a href="profile.php" class="card text-decoration-none border-0 bg-light h-100">
                                        <div class="card-body d-flex align-items-center">
                                            <i class="fas fa-building fa-2x text-primary me-3"></i>
                                            <div>
                                                <h6 class="mb-0 text-dark">Company Profile</h6>
                                                <p class="text-muted small mb-0">Update company details</p>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-12 mb-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Job Status Overview</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="jobStatusChart" height="180"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Job Status Chart
    document.addEventListener('DOMContentLoaded', function() {
        const jobStatusChart = document.getElementById('jobStatusChart');
        
        new Chart(jobStatusChart, {
            type: 'doughnut',
            data: {
                labels: ['Active', 'Filled', 'Closed', 'Draft'],
                datasets: [{
                    data: [
                        <?php echo $stats['active_jobs'] ?? 0; ?>, 
                        <?php echo $stats['filled_jobs'] ?? 0; ?>, 
                        <?php echo $stats['closed_jobs'] ?? 0; ?>, 
                        <?php echo $stats['draft_jobs'] ?? 0; ?>
                    ],
                    backgroundColor: [
                        '#0d6efd', // Primary (Active)
                        '#198754', // Success (Filled)
                        '#6c757d', // Secondary (Closed)
                        '#ffc107'  // Warning (Draft)
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    });
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>