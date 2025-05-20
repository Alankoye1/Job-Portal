<?php
// Set page title
$page_title = "Manage Jobs";
$page_header = "Manage Job Listings";

// Include database connection
require_once '../config/db.php';
// Include functions
require_once '../includes/functions.php';

// Check if user is logged in and is an employer
requireEmployer();

// Get filter parameters
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query
$query = "SELECT j.*, 
         (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id) as applications 
         FROM jobs j 
         WHERE j.employer_id = ?";
$params = [$_SESSION['user_id']];
$types = "i";

// Add filters
if (!empty($status)) {
    $query .= " AND j.status = ?";
    $params[] = $status;
    $types .= "s";
}

if (!empty($search)) {
    $query .= " AND j.title LIKE ?";
    $search_param = "%$search%";
    $params[] = $search_param;
    $types .= "s";
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
$query .= " ORDER BY j.created_at DESC LIMIT ?, ?";
$params[] = $offset;
$params[] = $per_page;
$types .= "ii";

// Execute the query
$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Get job status counts
$status_counts_query = "SELECT 
                        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count,
                        SUM(CASE WHEN status = 'filled' THEN 1 ELSE 0 END) as filled_count,
                        SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed_count,
                        SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_count
                        FROM jobs 
                        WHERE employer_id = ?";
$stmt = $conn->prepare($status_counts_query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$counts_result = $stmt->get_result();
$status_counts = $counts_result->fetch_assoc();

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
                        All Jobs <span class="badge bg-secondary ms-1"><?php echo array_sum($status_counts); ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $status === 'active' ? 'active' : ''; ?>" href="?status=active">
                        Active <span class="badge bg-secondary ms-1"><?php echo $status_counts['active_count']; ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $status === 'filled' ? 'active' : ''; ?>" href="?status=filled">
                        Filled <span class="badge bg-secondary ms-1"><?php echo $status_counts['filled_count']; ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $status === 'closed' ? 'active' : ''; ?>" href="?status=closed">
                        Closed <span class="badge bg-secondary ms-1"><?php echo $status_counts['closed_count']; ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $status === 'draft' ? 'active' : ''; ?>" href="?status=draft">
                        Drafts <span class="badge bg-secondary ms-1"><?php echo $status_counts['draft_count']; ?></span>
                    </a>
                </li>
            </ul>
        </div>
        <div class="col-md-4">
            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="get" class="d-flex">
                <?php if (!empty($status)): ?>
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($status); ?>">
                <?php endif; ?>
                <input type="text" class="form-control me-2" name="search" placeholder="Search job titles..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-outline-primary">
                    <i class="fas fa-search"></i>
                </button>
            </form>
        </div>
    </div>
    
    <!-- Jobs table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <?php if($result && $result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Job Title</th>
                                <th scope="col">Status</th>
                                <th scope="col">Applications</th>
                                <th scope="col">Views</th>
                                <th scope="col">Posted Date</th>
                                <th scope="col">Expires</th>
                                <th scope="col" class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($job = $result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($job['title']); ?></h6>
                                            <small class="text-muted">
                                                <?php if($job['job_type']): ?>
                                                    <span class="badge bg-primary me-1"><?php echo htmlspecialchars(isset(getEmploymentTypes()[$job['job_type']]) ? getEmploymentTypes()[$job['job_type']] : $job['job_type']); ?></span>
                                                <?php endif; ?>
                                                <?php if($job['location']): ?>
                                                    <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($job['location']); ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                        $status_badges = [
                                            'active' => 'bg-success',
                                            'filled' => 'bg-primary',
                                            'closed' => 'bg-secondary',
                                            'draft' => 'bg-warning text-dark'
                                        ];
                                        $badge_class = isset($status_badges[$job['status']]) ? $status_badges[$job['status']] : 'bg-secondary';
                                        ?>
                                        <span class="badge <?php echo $badge_class; ?>">
                                            <?php echo ucfirst($job['status']); ?>
                                        </span>
                                        <?php if($job['featured']): ?>
                                            <span class="badge bg-info text-dark ms-1">
                                                <i class="fas fa-star me-1"></i> Featured
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $job['applications']; ?></td>
                                    <td><?php echo $job['views']; ?></td>
                                    <td><?php echo formatDate($job['created_at']); ?></td>
                                    <td>
                                        <?php if($job['status'] === 'active'): ?>
                                            <?php 
                                            $expires_date = new DateTime($job['expires_at']);
                                            $now = new DateTime();
                                            $days_remaining = $now->diff($expires_date)->days;
                                            
                                            if($expires_date < $now): ?>
                                                <span class="text-danger">Expired</span>
                                            <?php elseif($days_remaining <= 5): ?>
                                                <span class="text-warning"><?php echo $days_remaining; ?> day<?php echo $days_remaining != 1 ? 's' : ''; ?> left</span>
                                            <?php else: ?>
                                                <?php echo formatDate($job['expires_at']); ?>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="jobAction<?php echo $job['id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                                Actions
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="jobAction<?php echo $job['id']; ?>">
                                                <li><a class="dropdown-item" href="../job-details.php?id=<?php echo $job['id']; ?>"><i class="fas fa-eye me-2"></i> View</a></li>
                                                <li><a class="dropdown-item" href="edit-job.php?id=<?php echo $job['id']; ?>"><i class="fas fa-edit me-2"></i> Edit</a></li>
                                                <li><a class="dropdown-item" href="applications.php?job_id=<?php echo $job['id']; ?>"><i class="fas fa-users me-2"></i> View Applications (<?php echo $job['applications']; ?>)</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                
                                                <?php if($job['status'] == 'active'): ?>
                                                    <li><a class="dropdown-item text-warning" href="mark-job.php?id=<?php echo $job['id']; ?>&status=filled"><i class="fas fa-check-circle me-2"></i> Mark as Filled</a></li>
                                                    <li><a class="dropdown-item text-danger" href="mark-job.php?id=<?php echo $job['id']; ?>&status=closed"><i class="fas fa-times-circle me-2"></i> Close Job</a></li>
                                                <?php elseif($job['status'] == 'draft'): ?>
                                                    <li><a class="dropdown-item text-success" href="mark-job.php?id=<?php echo $job['id']; ?>&status=active"><i class="fas fa-paper-plane me-2"></i> Publish</a></li>
                                                <?php elseif($job['status'] == 'closed' || $job['status'] == 'filled'): ?>
                                                    <li><a class="dropdown-item text-success" href="mark-job.php?id=<?php echo $job['id']; ?>&status=active"><i class="fas fa-redo me-2"></i> Reactivate</a></li>
                                                <?php endif; ?>
                                                
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item text-danger" href="delete-job.php?id=<?php echo $job['id']; ?>" onclick="return confirm('Are you sure you want to delete this job? This action cannot be undone.');"><i class="fas fa-trash-alt me-2"></i> Delete</a></li>
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
                        <nav aria-label="Job listings pagination">
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
                    <i class="fas fa-briefcase fa-4x text-muted mb-3"></i>
                    <h4>No jobs found</h4>
                    <p class="text-muted">
                        <?php if (!empty($search)): ?>
                            No jobs match your search criteria. Try different keywords.
                        <?php elseif (!empty($status)): ?>
                            You don't have any <?php echo $status; ?> jobs at the moment.
                        <?php else: ?>
                            You haven't posted any jobs yet. Create your first job posting.
                        <?php endif; ?>
                    </p>
                    <a href="post-job.php" class="btn btn-primary mt-2">
                        <i class="fas fa-plus-circle me-2"></i> Post a New Job
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Job Management Tips -->
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-white">
            <h5 class="mb-0">Tips for Managing Your Job Postings</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3 mb-md-0">
                    <div class="d-flex">
                        <div class="me-3 text-primary">
                            <i class="fas fa-pencil-alt fa-2x"></i>
                        </div>
                        <div>
                            <h6>Keep Descriptions Clear</h6>
                            <p class="text-muted mb-0">Well-written job descriptions with clear requirements attract more qualified candidates.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3 mb-md-0">
                    <div class="d-flex">
                        <div class="me-3 text-primary">
                            <i class="fas fa-bell fa-2x"></i>
                        </div>
                        <div>
                            <h6>Respond Promptly</h6>
                            <p class="text-muted mb-0">Review applications quickly. The best candidates are often hired within 10 days.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex">
                        <div class="me-3 text-primary">
                            <i class="fas fa-chart-line fa-2x"></i>
                        </div>
                        <div>
                            <h6>Track Performance</h6>
                            <p class="text-muted mb-0">Monitor views and applications to optimize your job postings and attract more candidates.</p>
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