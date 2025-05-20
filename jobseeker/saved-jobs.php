<?php
// Set page title
$page_title = "Saved Jobs";
$page_header = "My Saved Jobs";

// Include database connection
require_once '../config/db.php';
// Include functions
require_once '../includes/functions.php';

// Check if user is logged in and is a job seeker
requireJobSeeker();

// Get pagination parameters
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get all saved jobs for this user
$query = "SELECT sj.*, j.title as job_title, j.location as job_location, j.job_type,
                 j.salary_min, j.salary_max, j.salary_period, j.created_at as job_posted_at,
                 e.company_name, e.logo
          FROM saved_jobs sj
          JOIN jobs j ON sj.job_id = j.id
          JOIN employers e ON j.employer_id = e.id
          WHERE sj.jobseeker_id = ?
          ORDER BY sj.created_at DESC
          LIMIT ?, ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("iii", $_SESSION['user_id'], $offset, $per_page);
$stmt->execute();
$result = $stmt->get_result();

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM saved_jobs WHERE jobseeker_id = ?";
$stmt = $conn->prepare($count_query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$count_result = $stmt->get_result();
$count_data = $count_result->fetch_assoc();
$total_rows = $count_data['total'];
$total_pages = ceil($total_rows / $per_page);

// Include header
include_once '../includes/header.php';
?>

<div class="container mb-5">
    <!-- Page header with action buttons -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2 mb-0"><?php echo $page_header; ?></h1>
        <a href="../browse-jobs.php" class="btn btn-primary">
            <i class="fas fa-search me-2"></i> Browse More Jobs
        </a>
    </div>
    
    <!-- Saved jobs list -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <?php if($result && $result->num_rows > 0): ?>
                <div class="list-group list-group-flush">
                    <?php while($job = $result->fetch_assoc()): ?>
                        <div class="list-group-item p-3">
                            <div class="row">
                                <div class="col-md-9 mb-3 mb-md-0">
                                    <div class="d-flex">
                                        <div class="company-logo me-3 bg-light rounded p-2" style="width: 60px; height: 60px;">
                                            <?php if($job['logo']): ?>
                                                <img src="/assets/uploads/logos/<?php echo htmlspecialchars($job['logo']); ?>" alt="<?php echo htmlspecialchars($job['company_name']); ?>" class="img-fluid">
                                            <?php else: ?>
                                                <div class="d-flex align-items-center justify-content-center h-100 bg-light rounded">
                                                    <i class="fas fa-building text-secondary fa-2x"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <h5 class="mb-1">
                                                <a href="../job-details.php?id=<?php echo $job['job_id']; ?>" class="text-decoration-none">
                                                    <?php echo htmlspecialchars($job['job_title']); ?>
                                                </a>
                                            </h5>
                                            <p class="company-name mb-1"><?php echo htmlspecialchars($job['company_name']); ?></p>
                                            
                                            <div class="d-flex flex-wrap align-items-center">
                                                <?php if($job['job_type']): ?>
                                                    <span class="badge bg-primary me-2 mb-1"><?php 
                                                        // Convert underscores to hyphens if needed
                                                        $job_type = str_replace('_', '-', $job['job_type']);
                                                        echo htmlspecialchars(isset(getEmploymentTypes()[$job_type]) ? getEmploymentTypes()[$job_type] : $job['job_type']); 
                                                    ?></span>
                                                <?php endif; ?>
                                                
                                                <?php if($job['job_location']): ?>
                                                    <span class="badge bg-secondary me-2 mb-1">
                                                        <i class="fas fa-map-marker-alt me-1"></i>
                                                        <?php echo htmlspecialchars($job['job_location']); ?>
                                                    </span>
                                                <?php endif; ?>
                                                
                                                <?php if($job['salary_min'] && $job['salary_max']): ?>
                                                    <span class="badge bg-success me-2 mb-1">
                                                        $<?php echo number_format($job['salary_min']); ?> - $<?php echo number_format($job['salary_max']); ?>
                                                        <?php if($job['salary_period']): ?>
                                                            <span class="text-lowercase">/ <?php echo htmlspecialchars($job['salary_period']); ?></span>
                                                        <?php endif; ?>
                                                    </span>
                                                <?php endif; ?>
                                                
                                                <span class="text-muted small mb-1">
                                                    <i class="far fa-clock me-1"></i>
                                                    Saved <?php echo timeAgo($job['created_at']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-3 d-flex align-items-center justify-content-md-end">
                                    <div class="btn-group">
                                        <a href="../job-details.php?id=<?php echo $job['job_id']; ?>" class="btn btn-outline-primary">
                                            <i class="fas fa-eye me-1"></i> View Job
                                        </a>
                                        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#removeSavedJobModal<?php echo $job['id']; ?>">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                    
                                    <!-- Remove Saved Job Confirmation Modal -->
                                    <div class="modal fade" id="removeSavedJobModal<?php echo $job['id']; ?>" tabindex="-1" aria-labelledby="removeSavedJobModalLabel<?php echo $job['id']; ?>" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="removeSavedJobModalLabel<?php echo $job['id']; ?>">Remove Saved Job</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>Are you sure you want to remove <strong><?php echo htmlspecialchars($job['job_title']); ?></strong> from your saved jobs?</p>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <a href="remove-saved-job.php?id=<?php echo $job['id']; ?>" class="btn btn-danger">Remove</a>
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
                        <nav aria-label="Saved jobs pagination">
                            <ul class="pagination justify-content-center mb-0">
                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                                
                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                if ($start_page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=1">1</a>
                                    </li>
                                    <?php if ($start_page > 2): ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">...</span>
                                        </li>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($end_page < $total_pages): ?>
                                    <?php if ($end_page < $total_pages - 1): ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">...</span>
                                        </li>
                                    <?php endif; ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $total_pages; ?>"><?php echo $total_pages; ?></a>
                                    </li>
                                <?php endif; ?>
                                
                                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-bookmark fa-4x text-muted mb-3"></i>
                    <h4>No saved jobs found</h4>
                    <p class="text-muted">You haven't saved any jobs yet. Browse jobs and click the bookmark icon to save jobs for later.</p>
                    <a href="../browse-jobs.php" class="btn btn-primary mt-2">
                        <i class="fas fa-search me-2"></i> Browse Jobs
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';
?> 