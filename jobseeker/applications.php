<?php
// Set page title
$page_title = "My Applications";
$page_header = "My Job Applications";

// Include database connection
require_once '../config/db.php';
// Include functions
require_once '../includes/functions.php';

// Check if user is logged in and is a job seeker
requireJobSeeker();

// Get filter parameters
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query
$query = "SELECT a.*, j.title as job_title, j.location as job_location, j.job_type,
                 e.company_name, e.logo
          FROM applications a
          JOIN jobs j ON a.job_id = j.id
          JOIN employers e ON j.employer_id = e.id
          WHERE a.jobseeker_id = ?";
$params = [$_SESSION['user_id']];
$types = "i";

// Add filters
if (!empty($status)) {
    $query .= " AND a.status = ?";
    $params[] = $status;
    $types .= "s";
}

if (!empty($search)) {
    $query .= " AND (j.title LIKE ? OR e.company_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
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
                        WHERE a.jobseeker_id = ?";
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
        <a href="../browse-jobs.php" class="btn btn-primary">
            <i class="fas fa-search me-2"></i> Find More Jobs
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
                <input type="text" class="form-control me-2" name="search" placeholder="Search applications..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-outline-primary">
                    <i class="fas fa-search"></i>
                </button>
            </form>
        </div>
    </div>
    
    <!-- Applications list -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <?php if($result && $result->num_rows > 0): ?>
                <div class="list-group list-group-flush">
                    <?php while($application = $result->fetch_assoc()): ?>
                        <div class="list-group-item p-3">
                            <div class="row">
                                <div class="col-md-8 mb-3 mb-md-0">
                                    <div class="d-flex">
                                        <div class="company-logo me-3 bg-light rounded p-2" style="width: 60px; height: 60px;">
                                            <?php if($application['logo']): ?>
                                                <img src="/assets/uploads/logos/<?php echo htmlspecialchars($application['logo']); ?>" alt="<?php echo htmlspecialchars($application['company_name']); ?>" class="img-fluid">
                                            <?php else: ?>
                                                <div class="d-flex align-items-center justify-content-center h-100 bg-light rounded">
                                                    <i class="fas fa-building text-secondary fa-2x"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <h5 class="mb-1">
                                                <a href="../job-details.php?id=<?php echo $application['job_id']; ?>" class="text-decoration-none">
                                                    <?php echo htmlspecialchars($application['job_title']); ?>
                                                </a>
                                            </h5>
                                            <p class="company-name mb-1"><?php echo htmlspecialchars($application['company_name']); ?></p>
                                            
                                            <div class="d-flex flex-wrap align-items-center">
                                                <?php if($application['job_type']): ?>
                                                    <span class="badge bg-primary me-2 mb-1"><?php echo htmlspecialchars(getEmploymentTypes()[$application['job_type']]); ?></span>
                                                <?php endif; ?>
                                                
                                                <?php if($application['job_location']): ?>
                                                    <span class="badge bg-secondary me-2 mb-1">
                                                        <i class="fas fa-map-marker-alt me-1"></i>
                                                        <?php echo htmlspecialchars($application['job_location']); ?>
                                                    </span>
                                                <?php endif; ?>
                                                
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
                                                <span class="badge <?php echo $badge_class; ?> me-2 mb-1">
                                                    <?php echo ucfirst($application['status']); ?>
                                                </span>
                                                
                                                <span class="text-muted small mb-1">
                                                    <i class="far fa-calendar-alt me-1"></i>
                                                    Applied <?php echo timeAgo($application['created_at']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4 d-flex align-items-center justify-content-md-end">
                                    <div class="btn-group">
                                        <a href="view-application.php?id=<?php echo $application['id']; ?>" class="btn btn-outline-primary">
                                            <i class="fas fa-eye me-1"></i> View
                                        </a>
                                        <a href="../job-details.php?id=<?php echo $application['job_id']; ?>" class="btn btn-outline-secondary">
                                            <i class="fas fa-briefcase me-1"></i> Job Details
                                        </a>
                                        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $application['id']; ?>">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                    
                                    <!-- Delete Confirmation Modal -->
                                    <div class="modal fade" id="deleteModal<?php echo $application['id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?php echo $application['id']; ?>" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="deleteModalLabel<?php echo $application['id']; ?>">Confirm Withdrawal</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>Are you sure you want to withdraw your application for <strong><?php echo htmlspecialchars($application['job_title']); ?></strong> at <strong><?php echo htmlspecialchars($application['company_name']); ?></strong>?</p>
                                                    <p class="text-danger mb-0">This action cannot be undone.</p>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <a href="withdraw-application.php?id=<?php echo $application['id']; ?>" class="btn btn-danger">Withdraw Application</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
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
                        <?php else: ?>
                            You haven't applied to any jobs yet.
                        <?php endif; ?>
                    </p>
                    <a href="../browse-jobs.php" class="btn btn-primary mt-2">
                        <i class="fas fa-search me-2"></i> Browse Jobs
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Application Tips -->
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-white">
            <h5 class="mb-0">Tips for Job Applications</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3 mb-md-0">
                    <div class="d-flex">
                        <div class="me-3 text-primary">
                            <i class="fas fa-file-alt fa-2x"></i>
                        </div>
                        <div>
                            <h6>Tailor Your Resume</h6>
                            <p class="text-muted mb-0">Customize your resume for each application to highlight relevant skills and experience.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3 mb-md-0">
                    <div class="d-flex">
                        <div class="me-3 text-primary">
                            <i class="fas fa-pen-fancy fa-2x"></i>
                        </div>
                        <div>
                            <h6>Write Strong Cover Letters</h6>
                            <p class="text-muted mb-0">Address why you're interested in the position and how your skills match the job requirements.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex">
                        <div class="me-3 text-primary">
                            <i class="fas fa-user-tie fa-2x"></i>
                        </div>
                        <div>
                            <h6>Prepare for Interviews</h6>
                            <p class="text-muted mb-0">Research the company and practice answering common interview questions for each opportunity.</p>
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