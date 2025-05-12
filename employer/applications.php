<?php
// Set page title
$page_title = "Applications";
$page_header = "Manage Applications";

// Include database connection
require_once '../config/db.php';
// Include functions
require_once '../includes/functions.php';

// Check if user is logged in and is an employer
requireEmployer();

// Get filter parameters
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$job_id = isset($_GET['job_id']) ? intval($_GET['job_id']) : 0;
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query
$query = "SELECT a.*, j.title as job_title, js.name as applicant_name, js.email as applicant_email, 
                 js.phone as applicant_phone, js.location as applicant_location 
          FROM applications a
          JOIN jobs j ON a.job_id = j.id
          JOIN jobseekers js ON a.jobseeker_id = js.id
          WHERE j.employer_id = ?";
$params = [$_SESSION['user_id']];
$types = "i";

// Add filters
if (!empty($status)) {
    $query .= " AND a.status = ?";
    $params[] = $status;
    $types .= "s";
}

if ($job_id > 0) {
    $query .= " AND a.job_id = ?";
    $params[] = $job_id;
    $types .= "i";
}

if (!empty($search)) {
    $query .= " AND (js.name LIKE ? OR j.title LIKE ? OR js.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

// Get total count for pagination
$count_query = $query;
$stmt = $conn->prepare($count_query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$count_result = $stmt->get_result();
$total_rows = $count_result->num_rows;
$total_pages = ceil($total_rows / $per_page);

// Add sorting and pagination
$query .= " ORDER BY a.created_at DESC LIMIT ?, ?";
$params[] = $offset;
$params[] = $per_page;
$types .= "ii";

// Execute the query
$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Get application status counts
$status_counts_query = "SELECT 
                        SUM(CASE WHEN a.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                        SUM(CASE WHEN a.status = 'reviewed' THEN 1 ELSE 0 END) as reviewed_count,
                        SUM(CASE WHEN a.status = 'interviewed' THEN 1 ELSE 0 END) as interviewed_count,
                        SUM(CASE WHEN a.status = 'offered' THEN 1 ELSE 0 END) as offered_count,
                        SUM(CASE WHEN a.status = 'hired' THEN 1 ELSE 0 END) as hired_count,
                        SUM(CASE WHEN a.status = 'rejected' THEN 1 ELSE 0 END) as rejected_count
                        FROM applications a
                        JOIN jobs j ON a.job_id = j.id
                        WHERE j.employer_id = ?";
$stmt = $conn->prepare($status_counts_query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$counts_result = $stmt->get_result();
$status_counts = $counts_result->fetch_assoc();

// Get job listings for filter dropdown
$jobs_query = "SELECT id, title FROM jobs WHERE employer_id = ? ORDER BY title ASC";
$stmt = $conn->prepare($jobs_query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$jobs_result = $stmt->get_result();

// Include header
include_once '../includes/header.php';
?>

<div class="container mb-5">
    <!-- Page header with action buttons -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2 mb-0"><?php echo $page_header; ?></h1>
        <a href="post-job.php" class="btn btn-primary">
            <i class="fas fa-plus-circle me-2"></i> Post a New Job
        </a>
    </div>
    
    <!-- Filter tabs and search -->
    <div class="row mb-4">
        <div class="col-md-8">
            <ul class="nav nav-pills">
                <li class="nav-item">
                    <a class="nav-link <?php echo empty($status) ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                        All <span class="badge bg-secondary ms-1"><?php echo array_sum($status_counts); ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $status === 'pending' ? 'active' : ''; ?>" href="?status=pending">
                        Pending <span class="badge bg-secondary ms-1"><?php echo $status_counts['pending_count']; ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $status === 'reviewed' ? 'active' : ''; ?>" href="?status=reviewed">
                        Reviewed <span class="badge bg-secondary ms-1"><?php echo $status_counts['reviewed_count']; ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $status === 'interviewed' ? 'active' : ''; ?>" href="?status=interviewed">
                        Interviewed <span class="badge bg-secondary ms-1"><?php echo $status_counts['interviewed_count']; ?></span>
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?php echo in_array($status, ['offered', 'hired', 'rejected']) ? 'active' : ''; ?>" data-bs-toggle="dropdown" href="#" role="button" aria-expanded="false">
                        More
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item <?php echo $status === 'offered' ? 'active' : ''; ?>" href="?status=offered">Offered (<?php echo $status_counts['offered_count']; ?>)</a></li>
                        <li><a class="dropdown-item <?php echo $status === 'hired' ? 'active' : ''; ?>" href="?status=hired">Hired (<?php echo $status_counts['hired_count']; ?>)</a></li>
                        <li><a class="dropdown-item <?php echo $status === 'rejected' ? 'active' : ''; ?>" href="?status=rejected">Rejected (<?php echo $status_counts['rejected_count']; ?>)</a></li>
                    </ul>
                </li>
            </ul>
        </div>
        <div class="col-md-4">
            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="get" class="d-flex">
                <?php if (!empty($status)): ?>
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($status); ?>">
                <?php endif; ?>
                <?php if ($job_id > 0): ?>
                    <input type="hidden" name="job_id" value="<?php echo $job_id; ?>">
                <?php endif; ?>
                <input type="text" class="form-control me-2" name="search" placeholder="Search applications..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-outline-primary">
                    <i class="fas fa-search"></i>
                </button>
            </form>
        </div>
    </div>
    
    <!-- Additional filter by job -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="get" class="row g-3 align-items-end">
                <?php if (!empty($status)): ?>
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($status); ?>">
                <?php endif; ?>
                <?php if (!empty($search)): ?>
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                <?php endif; ?>
                
                <div class="col-md-4">
                    <label for="job_id" class="form-label">Filter by Job</label>
                    <select class="form-select" id="job_id" name="job_id">
                        <option value="">All Jobs</option>
                        <?php if($jobs_result && $jobs_result->num_rows > 0): ?>
                            <?php while($job = $jobs_result->fetch_assoc()): ?>
                                <option value="<?php echo $job['id']; ?>" <?php echo ($job_id == $job['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($job['title']); ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="col-md-8">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter me-2"></i> Apply Filter
                        </button>
                        <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-undo me-2"></i> Reset Filters
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Applications table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <?php if($result && $result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Applicant</th>
                                <th scope="col">Job</th>
                                <th scope="col">Applied On</th>
                                <th scope="col">Status</th>
                                <th scope="col" class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($application = $result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar bg-light rounded-circle text-center me-2" style="width: 36px; height: 36px; line-height: 36px;">
                                                <i class="fas fa-user text-primary"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0"><?php echo htmlspecialchars($application['applicant_name']); ?></h6>
                                                <div class="small text-muted">
                                                    <span><?php echo htmlspecialchars($application['applicant_email']); ?></span>
                                                    <?php if($application['applicant_phone']): ?>
                                                        <span class="mx-1">•</span>
                                                        <span><?php echo htmlspecialchars($application['applicant_phone']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if($application['applicant_location']): ?>
                                                    <small class="text-muted">
                                                        <i class="fas fa-map-marker-alt me-1"></i>
                                                        <?php echo htmlspecialchars($application['applicant_location']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="../job-details.php?id=<?php echo $application['job_id']; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($application['job_title']); ?>
                                        </a>
                                    </td>
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
                                    <td class="text-end">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="applicationAction<?php echo $application['id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                                Actions
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="applicationAction<?php echo $application['id']; ?>">
                                                <li><a class="dropdown-item" href="view-application.php?id=<?php echo $application['id']; ?>"><i class="fas fa-eye me-2"></i> View Details</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><h6 class="dropdown-header">Update Status</h6></li>
                                                
                                                <?php 
                                                $current_status = $application['status'];
                                                $statuses = [
                                                    'pending' => 'Pending',
                                                    'reviewed' => 'Mark as Reviewed',
                                                    'interviewed' => 'Mark as Interviewed',
                                                    'offered' => 'Mark as Offered',
                                                    'hired' => 'Mark as Hired',
                                                    'rejected' => 'Reject Application'
                                                ];
                                                
                                                foreach($statuses as $status_key => $status_label):
                                                    if($status_key != $current_status):
                                                        $status_class = '';
                                                        if($status_key == 'rejected') $status_class = 'text-danger';
                                                        elseif($status_key == 'hired' || $status_key == 'offered') $status_class = 'text-success';
                                                ?>
                                                    <li>
                                                        <a class="dropdown-item <?php echo $status_class; ?>" 
                                                           href="update-application-status.php?id=<?php echo $application['id']; ?>&status=<?php echo $status_key; ?>">
                                                            <?php echo $status_label; ?>
                                                        </a>
                                                    </li>
                                                <?php 
                                                    endif;
                                                endforeach;
                                                ?>
                                                
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item" href="export-resume.php?id=<?php echo $application['id']; ?>"><i class="fas fa-download me-2"></i> Download Resume</a></li>
                                                <li><a class="dropdown-item text-danger" href="delete-application.php?id=<?php echo $application['id']; ?>" onclick="return confirm('Are you sure you want to delete this application?');"><i class="fas fa-trash-alt me-2"></i> Delete</a></li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if($total_pages > 1): ?>
                    <div class="pagination-container p-3">
                        <nav aria-label="Applications pagination">
                            <ul class="pagination justify-content-center mb-0">
                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                                
                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                if ($start_page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">1</a>
                                    </li>
                                    <?php if ($start_page > 2): ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">...</span>
                                        </li>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($end_page < $total_pages): ?>
                                    <?php if ($end_page < $total_pages - 1): ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">...</span>
                                        </li>
                                    <?php endif; ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>"><?php echo $total_pages; ?></a>
                                    </li>
                                <?php endif; ?>
                                
                                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-file-alt fa-4x text-muted mb-3"></i>
                    <h4>No applications found</h4>
                    <p class="text-muted">
                        <?php if (!empty($search)): ?>
                            No applications match your search criteria. Try different keywords.
                        <?php elseif (!empty($status)): ?>
                            You don't have any applications with status "<?php echo $status; ?>" at the moment.
                        <?php elseif ($job_id > 0): ?>
                            This job hasn't received any applications yet.
                        <?php else: ?>
                            You haven't received any job applications yet.
                        <?php endif; ?>
                    </p>
                    <a href="post-job.php" class="btn btn-primary mt-2">
                        <i class="fas fa-plus-circle me-2"></i> Post a New Job
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Application Management Tips -->
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-white">
            <h5 class="mb-0">Tips for Managing Applications</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3 mb-md-0">
                    <div class="d-flex">
                        <div class="me-3 text-primary">
                            <i class="fas fa-comments fa-2x"></i>
                        </div>
                        <div>
                            <h6>Provide Timely Feedback</h6>
                            <p class="text-muted mb-0">Responding promptly to applications improves candidate experience and your employer brand.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3 mb-md-0">
                    <div class="d-flex">
                        <div class="me-3 text-primary">
                            <i class="fas fa-tasks fa-2x"></i>
                        </div>
                        <div>
                            <h6>Stay Organized</h6>
                            <p class="text-muted mb-0">Use status updates to track where each candidate is in your hiring process.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex">
                        <div class="me-3 text-primary">
                            <i class="fas fa-check-circle fa-2x"></i>
                        </div>
                        <div>
                            <h6>Look Beyond the Resume</h6>
                            <p class="text-muted mb-0">Consider motivation and cultural fit in addition to technical qualifications.</p>
                        </div>
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