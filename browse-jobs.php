<?php
// Set page title
$page_title = "Browse Jobs";
$page_header = "Browse Jobs";

// Include database connection
require_once 'config/db.php';
// Include functions
require_once 'includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get filter parameters
$keyword = isset($_GET['keyword']) ? sanitizeInput($_GET['keyword']) : '';
$location = isset($_GET['location']) ? sanitizeInput($_GET['location']) : '';
$category = isset($_GET['category']) ? sanitizeInput($_GET['category']) : '';
$job_type = isset($_GET['job_type']) ? sanitizeInput($_GET['job_type']) : '';
$experience = isset($_GET['experience']) ? sanitizeInput($_GET['experience']) : '';
$featured = isset($_GET['featured']) && $_GET['featured'] == '1' ? 1 : 0;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query
$query = "SELECT j.*, e.company_name, e.logo 
          FROM jobs j 
          JOIN employers e ON j.employer_id = e.id 
          WHERE j.status = 'active' AND j.expires_at > NOW()";
$count_query = "SELECT COUNT(*) as total FROM jobs j WHERE j.status = 'active' AND j.expires_at > NOW()";
$params = [];
$types = "";

// Add filters
if (!empty($keyword)) {
    $query .= " AND (j.title LIKE ? OR j.description LIKE ?)";
    $count_query .= " AND (j.title LIKE ? OR j.description LIKE ?)";
    $keyword_param = "%$keyword%";
    $params[] = $keyword_param;
    $params[] = $keyword_param;
    $types .= "ss";
}

if (!empty($location)) {
    $query .= " AND j.location LIKE ?";
    $count_query .= " AND j.location LIKE ?";
    $location_param = "%$location%";
    $params[] = $location_param;
    $types .= "s";
}

if (!empty($category)) {
    $query .= " AND j.category = ?";
    $count_query .= " AND j.category = ?";
    $params[] = $category;
    $types .= "s";
}

if (!empty($job_type)) {
    $query .= " AND j.job_type = ?";
    $count_query .= " AND j.job_type = ?";
    $params[] = $job_type;
    $types .= "s";
}

if (!empty($experience)) {
    $query .= " AND j.experience_level = ?";
    $count_query .= " AND j.experience_level = ?";
    $params[] = $experience;
    $types .= "s";
}

if ($featured) {
    $query .= " AND j.featured = 1";
    $count_query .= " AND j.featured = 1";
}

// Add sorting
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
switch ($sort) {
    case 'oldest':
        $query .= " ORDER BY j.featured DESC, j.created_at ASC";
        break;
    case 'a-z':
        $query .= " ORDER BY j.featured DESC, j.title ASC";
        break;
    case 'z-a':
        $query .= " ORDER BY j.featured DESC, j.title DESC";
        break;
    default: // newest
        $query .= " ORDER BY j.featured DESC, j.created_at DESC";
        break;
}

// Add pagination
$query .= " LIMIT ?, ?";
$params[] = $offset;
$params[] = $per_page;
$types .= "ii";

// Prepare and execute count query
$count_stmt = $conn->prepare($count_query);
if (!empty($types)) {
    // Calculate correct type string length for count query parameters
    $count_types = substr($types, 0, strlen($types) - 2); // Remove the 'ii' added for pagination
    $count_params = array_slice($params, 0, -2); // Remove last 2 elements (offset and per_page)
    $count_stmt->bind_param($count_types, ...$count_params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_rows = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $per_page);

// Prepare and execute main query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Get job categories for filter
$categories = getJobCategories();
$employment_types = getEmploymentTypes();
$experience_levels = getExperienceLevels();

// Include header
include_once 'includes/header.php';
?>

<div class="container mb-5">
    <!-- Filter Banner -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 bg-primary text-white shadow">
                <div class="card-body p-4">
                    <form id="job-filter-form" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="get" class="row g-3">
                        <div class="col-md-4">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-primary"></i></span>
                                <input type="text" class="form-control border-start-0" name="keyword" placeholder="Job title, keywords" value="<?php echo htmlspecialchars($keyword); ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="fas fa-map-marker-alt text-primary"></i></span>
                                <input type="text" class="form-control border-start-0" name="location" id="location" placeholder="Location" value="<?php echo htmlspecialchars($location); ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" name="job_type">
                                <option value="">All Job Types</option>
                                <?php foreach($employment_types as $key => $value): ?>
                                    <option value="<?php echo $key; ?>" <?php echo ($job_type == $key) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($value); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-light text-primary w-100">
                                <i class="fas fa-filter me-2"></i> Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Sidebar Filters -->
        <div class="col-lg-3 mb-4 mb-lg-0">
            <div class="card filter-card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Refine Results</h5>
                </div>
                <div class="card-body">
                    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="get">
                        <!-- Preserve existing filters -->
                        <?php if($keyword): ?>
                            <input type="hidden" name="keyword" value="<?php echo htmlspecialchars($keyword); ?>">
                        <?php endif; ?>
                        <?php if($location): ?>
                            <input type="hidden" name="location" value="<?php echo htmlspecialchars($location); ?>">
                        <?php endif; ?>
                        
                        <!-- Category Filter -->
                        <div class="mb-4">
                            <h6 class="filter-title">Categories</h6>
                            <select class="form-select" name="category" onchange="this.form.submit()">
                                <option value="">All Categories</option>
                                <?php foreach($categories as $key => $value): ?>
                                    <option value="<?php echo $key; ?>" <?php echo ($category == $key) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($value); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Job Type Filter -->
                        <div class="mb-4">
                            <h6 class="filter-title">Job Type</h6>
                            <select class="form-select" name="job_type" onchange="this.form.submit()">
                                <option value="">All Job Types</option>
                                <?php foreach($employment_types as $key => $value): ?>
                                    <option value="<?php echo $key; ?>" <?php echo ($job_type == $key) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($value); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Experience Level Filter -->
                        <div class="mb-4">
                            <h6 class="filter-title">Experience Level</h6>
                            <select class="form-select" name="experience" onchange="this.form.submit()">
                                <option value="">All Experience Levels</option>
                                <?php foreach($experience_levels as $key => $value): ?>
                                    <option value="<?php echo $key; ?>" <?php echo ($experience == $key) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($value); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Featured Jobs Filter -->
                        <div class="mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="featured" name="featured" value="1" 
                                       <?php echo $featured ? 'checked' : ''; ?> onchange="this.form.submit()">
                                <label class="form-check-label" for="featured">
                                    Featured Jobs Only
                                </label>
                            </div>
                        </div>
                        
                        <!-- Reset Filters -->
                        <div class="text-center">
                            <a href="browse-jobs.php" id="clear-filters" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-undo me-1"></i> Reset Filters
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Job Listings -->
        <div class="col-lg-9">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0">
                    <?php if($total_rows == 0): ?>
                        No jobs found
                    <?php else: ?>
                        Found <?php echo $total_rows; ?> job<?php echo $total_rows != 1 ? 's' : ''; ?>
                    <?php endif; ?>
                </h4>
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="sortDropdown" data-bs-toggle="dropdown">
                        <i class="fas fa-sort me-1"></i> Sort By
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'newest'])); ?>">Newest First</a></li>
                        <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'oldest'])); ?>">Oldest First</a></li>
                        <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'a-z'])); ?>">A-Z</a></li>
                        <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'z-a'])); ?>">Z-A</a></li>
                    </ul>
                </div>
            </div>
            
            <?php if($result && $result->num_rows > 0): ?>
                <?php while($job = $result->fetch_assoc()): ?>
                    <div class="card job-card mb-3 border-0 shadow-sm fade-in">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="d-flex">
                                        <div class="company-logo me-3 bg-light rounded p-2" style="width: 70px; height: 70px;">
                                            <?php if($job['logo']): ?>
                                                <img src="/assets/uploads/logos/<?php echo htmlspecialchars($job['logo']); ?>" alt="<?php echo htmlspecialchars($job['company_name']); ?>" class="img-fluid">
                                            <?php else: ?>
                                                <div class="d-flex align-items-center justify-content-center h-100 bg-light rounded">
                                                    <i class="fas fa-building text-secondary fa-2x"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <h5 class="job-title mb-1">
                                                <a href="job-details.php?id=<?php echo $job['id']; ?>" class="text-decoration-none">
                                                    <?php echo htmlspecialchars($job['title']); ?>
                                                </a>
                                            </h5>
                                            <p class="company-name mb-2"><?php echo htmlspecialchars($job['company_name']); ?></p>
                                            
                                            <div class="job-meta mb-2">
                                                <?php if($job['job_type']): ?>
                                                    <span class="badge bg-primary mb-1 me-1"><?php echo htmlspecialchars(getEmploymentTypes()[$job['job_type']]); ?></span>
                                                <?php endif; ?>
                                                
                                                <?php if($job['location']): ?>
                                                    <span class="badge bg-secondary mb-1 me-1">
                                                        <i class="fas fa-map-marker-alt me-1"></i>
                                                        <?php echo htmlspecialchars($job['location']); ?>
                                                    </span>
                                                <?php endif; ?>
                                                
                                                <?php if($job['salary_min'] && $job['salary_max']): ?>
                                                    <span class="badge bg-success mb-1">
                                                        <i class="fas fa-money-bill-wave me-1"></i>
                                                        $<?php echo number_format($job['salary_min']); ?> - $<?php echo number_format($job['salary_max']); ?>
                                                        <?php if($job['salary_period']): ?>
                                                            /<?php echo htmlspecialchars($job['salary_period']); ?>
                                                        <?php endif; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4 mt-3 mt-md-0 d-flex flex-column justify-content-between">
                                    <div class="d-flex justify-content-md-end mb-2">
                                        <?php if($job['featured']): ?>
                                            <span class="badge bg-warning text-dark me-2">
                                                <i class="fas fa-star me-1"></i> Featured
                                            </span>
                                        <?php endif; ?>
                                        
                                        <span class="text-muted">
                                            <i class="far fa-clock me-1"></i>
                                            <?php echo timeAgo($job['created_at']); ?>
                                        </span>
                                    </div>
                                    
                                    <div class="d-flex justify-content-md-end">
                                        <a href="job-details.php?id=<?php echo $job['id']; ?>" class="btn btn-outline-primary">
                                            View Details
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <p class="job-description text-muted mb-0">
                                    <?php echo truncateText(htmlspecialchars($job['description']), 150); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
                
                <!-- Pagination -->
                <?php if($total_pages > 1): ?>
                    <nav aria-label="Job listings pagination" class="mt-4">
                        <ul class="pagination justify-content-center">
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
                <?php endif; ?>
                
            <?php else: ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-5 text-center">
                        <i class="fas fa-search fa-4x text-muted mb-4"></i>
                        <h3>No Jobs Found</h3>
                        <p class="text-muted mb-4">We couldn't find any jobs matching your criteria. Try adjusting your filters or search for something else.</p>
                        <a href="browse-jobs.php" class="btn btn-outline-primary">
                            <i class="fas fa-undo me-2"></i> Reset Filters
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include footer
include_once 'includes/footer.php';
?>