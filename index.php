<?php
// Set page title
$page_title = "Home";

// Include database connection
require_once 'config/db.php';
// Include functions
require_once 'includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get latest job listings
$latest_jobs_query = "SELECT * 
                     FROM jobs 
                     WHERE status = 'active' AND expires_at > NOW() 
                     ORDER BY created_at DESC 
                     LIMIT 6";
$latest_jobs_result = $conn->query($latest_jobs_query);

// Get featured job listings
$featured_jobs_query = "SELECT * 
                       FROM jobs  
                       WHERE status = 'active' AND featured = 1 AND expires_at > NOW() 
                       ORDER BY created_at DESC 
                       LIMIT 3";
$featured_jobs_result = $conn->query($featured_jobs_query);
if (!$featured_jobs_result) {
    echo "Error: " . $conn->error;
}

// Get job categories with counts
$categories_query = "SELECT category, COUNT(*) as job_count 
                    FROM jobs 
                    WHERE status = 'active' AND expires_at > NOW() 
                    GROUP BY category 
                    ORDER BY job_count DESC 
                    LIMIT 8";
$categories_result = $conn->query($categories_query);

// Get total counts for statistics
$stats_query = "SELECT 
                (SELECT COUNT(*) FROM jobs WHERE status = 'active' AND expires_at > NOW()) as active_jobs,
                (SELECT COUNT(*) FROM employers) as employers,
                (SELECT COUNT(*) FROM jobseekers) as jobseekers,
                (SELECT COUNT(*) FROM applications) as applications";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Include header
include_once 'includes/header.php';
?>

<!-- Hero Section -->
<section class="hero-section bg-primary text-white py-5 mb-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6 mb-5 mb-lg-0">
                <h1 class="display-4 fw-bold mb-3">Find Your Dream Job Today</h1>
                <p class="lead mb-4">Connect with top employers and discover opportunities that match your skills and career goals.</p>
                
                <!-- Search Form -->
                <form action="browse-jobs.php" method="get" class="job-search-form bg-white p-3 rounded shadow-sm">
                    <div class="row g-2">
                        <div class="col-md-5">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-primary"></i></span>
                                <input type="text" class="form-control border-start-0" name="keyword" placeholder="Job title, keywords">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="fas fa-map-marker-alt text-primary"></i></span>
                                <input type="text" class="form-control border-start-0" name="location" id="location" placeholder="Location">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search me-2"></i> Search
                            </button>
                        </div>
                    </div>
                </form>
                
                <!-- Popular Searches -->
                <div class="mt-3 popular-searches">
                    <span class="text-white-50">Popular Searches:</span>
                    <a href="browse-jobs.php?keyword=remote" class="badge bg-white text-primary me-2">Remote</a>
                    <a href="browse-jobs.php?keyword=developer" class="badge bg-white text-primary me-2">Developer</a>
                    <a href="browse-jobs.php?keyword=marketing" class="badge bg-white text-primary me-2">Marketing</a>
                    <a href="browse-jobs.php?keyword=healthcare" class="badge bg-white text-primary me-2">Healthcare</a>
                </div>
            </div>
            <div class="col-lg-6 text-center">
                <img src="https://images.pexels.com/photos/3184291/pexels-photo-3184291.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=2" alt="Job Seekers" class="img-fluid rounded shadow-lg" style="max-height: 400px;">
            </div>
        </div>
    </div>
</section>

<!-- Featured Jobs Section -->
<section class="featured-jobs mb-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="section-title">Featured Jobs</h2>
            <a href="browse-jobs.php?featured=1" class="btn btn-outline-primary">
                View All <i class="fas fa-arrow-right ms-2"></i>
            </a>
        </div>
        
        <div class="row">
            <?php if($featured_jobs_result && $featured_jobs_result->num_rows > 0): ?>
                <?php while($job = $featured_jobs_result->fetch_assoc()): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card job-card h-100 border-0 shadow-sm">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="company-logo me-3 bg-light rounded p-2" style="width: 60px; height: 60px;">
                                        <div class="d-flex align-items-center justify-content-center h-100 bg-light rounded">
                                            <i class="fas fa-building text-secondary fa-2x"></i>
                                        </div>
                                    </div>
                                    <div>
                                        <h5 class="job-title mb-1">
                                            <a href="job-details.php?id=<?php echo $job['id']; ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($job['title']); ?>
                                            </a>
                                        </h5>
                                        <p class="company-name mb-0">Company</p>
                                    </div>
                                </div>
                                
                                <div class="job-meta mb-3">
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
                                
                                <p class="job-description text-muted mb-3">
                                    <?php echo truncateText(htmlspecialchars($job['description']), 120); ?>
                                </p>
                                
                                <div class="mt-auto d-flex justify-content-between align-items-center">
                                    <span class="text-muted small">
                                        <i class="far fa-clock me-1"></i>
                                        <?php echo timeAgo($job['created_at']); ?>
                                    </span>
                                    <a href="job-details.php?id=<?php echo $job['id']; ?>" class="btn btn-sm btn-outline-primary">View Details</a>
                                </div>
                            </div>
                            <div class="card-footer bg-white border-top-0 text-end">
                                <span class="badge bg-warning text-dark">
                                    <i class="fas fa-star me-1"></i> Featured
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i> No featured jobs available at the moment. Check back soon!
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Job Categories Section -->
<section class="job-categories py-5 bg-light mb-5">
    <div class="container">
        <h2 class="section-title text-center mb-5">Browse Jobs by Category</h2>
        
        <div class="row">
            <?php 
            $category_icons = [
                'technology' => 'fas fa-laptop-code',
                'healthcare' => 'fas fa-heartbeat',
                'education' => 'fas fa-graduation-cap',
                'finance' => 'fas fa-chart-line',
                'marketing' => 'fas fa-bullhorn',
                'engineering' => 'fas fa-hard-hat',
                'creative' => 'fas fa-paint-brush',
                'hospitality' => 'fas fa-hotel',
                'legal' => 'fas fa-balance-scale',
                'administrative' => 'fas fa-tasks',
                'retail' => 'fas fa-shopping-cart',
                'manufacturing' => 'fas fa-industry',
                'transport' => 'fas fa-truck',
                'hr' => 'fas fa-users',
                'other' => 'fas fa-briefcase'
            ];
            
            if($categories_result && $categories_result->num_rows > 0): 
                while($category = $categories_result->fetch_assoc()): 
                    $category_name = $category['category'];
                    $category_display = isset(getJobCategories()[$category_name]) 
                        ? getJobCategories()[$category_name] 
                        : ucfirst($category_name);
                    $icon = isset($category_icons[$category_name]) 
                        ? $category_icons[$category_name] 
                        : 'fas fa-briefcase';
            ?>
                <div class="col-md-3 col-sm-6 mb-4">
                    <a href="browse-jobs.php?category=<?php echo urlencode($category_name); ?>" class="text-decoration-none">
                        <div class="card category-card h-100 border-0 shadow-sm">
                            <div class="card-body text-center p-4">
                                <div class="category-icon bg-primary text-white rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 70px; height: 70px;">
                                    <i class="<?php echo $icon; ?> fa-2x"></i>
                                </div>
                                <h5 class="category-name mb-2"><?php echo htmlspecialchars($category_display); ?></h5>
                                <p class="job-count text-muted mb-0">
                                    <?php echo $category['job_count']; ?> <?php echo $category['job_count'] == 1 ? 'Job' : 'Jobs'; ?>
                                </p>
                            </div>
                        </div>
                    </a>
                </div>
            <?php 
                endwhile; 
            else: 
            ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i> No job categories available at the moment. Check back soon!
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="text-center mt-4">
            <a href="browse-jobs.php" class="btn btn-outline-primary">
                View All Categories <i class="fas fa-arrow-right ms-2"></i>
            </a>
        </div>
    </div>
</section>

<!-- Latest Jobs Section -->
<section class="latest-jobs mb-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="section-title">Latest Job Openings</h2>
            <a href="browse-jobs.php" class="btn btn-outline-primary">
                View All <i class="fas fa-arrow-right ms-2"></i>
            </a>
        </div>
        
        <div class="row">
            <?php if($latest_jobs_result && $latest_jobs_result->num_rows > 0): ?>
                <?php while($job = $latest_jobs_result->fetch_assoc()): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card job-card h-100 border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex">
                                    <div class="company-logo me-3 bg-light rounded p-2" style="width: 60px; height: 60px;">
                                        <div class="d-flex align-items-center justify-content-center h-100 bg-light rounded">
                                            <i class="fas fa-building text-secondary fa-2x"></i>
                                        </div>
                                    </div>
                                    <div>
                                        <h5 class="job-title mb-1">
                                            <a href="job-details.php?id=<?php echo $job['id']; ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($job['title']); ?>
                                            </a>
                                        </h5>
                                        <p class="company-name mb-0">Company</p>
                                    </div>
                                </div>
                                
                                <div class="job-meta my-3">
                                    <?php if($job['job_type']): ?>
                                        <span class="badge bg-primary mb-1 me-1"><?php echo htmlspecialchars(getEmploymentTypes()[$job['job_type']]); ?></span>
                                    <?php endif; ?>
                                    
                                    <?php if($job['location']): ?>
                                        <span class="badge bg-secondary mb-1 me-1">
                                            <i class="fas fa-map-marker-alt me-1"></i>
                                            <?php echo htmlspecialchars($job['location']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted small">
                                        <i class="far fa-clock me-1"></i>
                                        <?php echo timeAgo($job['created_at']); ?>
                                    </span>
                                    <a href="job-details.php?id=<?php echo $job['id']; ?>" class="btn btn-sm btn-outline-primary">View Details</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i> No jobs available at the moment. Check back soon!
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Statistics Section -->
<section class="statistics py-5 bg-primary text-white mb-5">
    <div class="container">
        <h2 class="section-title text-center text-white mb-5">JobConnect in Numbers</h2>
        
        <div class="row text-center">
            <div class="col-md-3 col-6 mb-4 mb-md-0">
                <div class="stat-card bg-primary border border-light rounded p-4">
                    <i class="fas fa-briefcase fa-3x mb-3"></i>
                    <h3 class="stat-value mb-2">
                        <span class="stat-counter" data-target="<?php echo $stats['active_jobs']; ?>">0</span>+
                    </h3>
                    <p class="stat-label mb-0">Active Jobs</p>
                </div>
            </div>
            
            <div class="col-md-3 col-6 mb-4 mb-md-0">
                <div class="stat-card bg-primary border border-light rounded p-4">
                    <i class="fas fa-building fa-3x mb-3"></i>
                    <h3 class="stat-value mb-2">
                        <span class="stat-counter" data-target="<?php echo $stats['employers']; ?>">0</span>+
                    </h3>
                    <p class="stat-label mb-0">Companies</p>
                </div>
            </div>
            
            <div class="col-md-3 col-6">
                <div class="stat-card bg-primary border border-light rounded p-4">
                    <i class="fas fa-user-tie fa-3x mb-3"></i>
                    <h3 class="stat-value mb-2">
                        <span class="stat-counter" data-target="<?php echo $stats['jobseekers']; ?>">0</span>+
                    </h3>
                    <p class="stat-label mb-0">Job Seekers</p>
                </div>
            </div>
            
            <div class="col-md-3 col-6">
                <div class="stat-card bg-primary border border-light rounded p-4">
                    <i class="fas fa-paper-plane fa-3x mb-3"></i>
                    <h3 class="stat-value mb-2">
                        <span class="stat-counter" data-target="<?php echo $stats['applications']; ?>">0</span>+
                    </h3>
                    <p class="stat-label mb-0">Applications</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- How It Works Section -->
<section class="how-it-works mb-5">
    <div class="container">
        <h2 class="section-title text-center mb-5">How JobConnect Works</h2>
        
        <div class="row">
            <div class="col-md-4 mb-4 mb-md-0">
                <div class="card h-100 border-0 shadow-sm text-center">
                    <div class="card-body p-4">
                        <div class="step-icon bg-primary text-white rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                            <i class="fas fa-user-plus fa-2x"></i>
                        </div>
                        <h4 class="mb-3">Create an Account</h4>
                        <p class="text-muted">Sign up as a job seeker or employer. Complete your profile with relevant information to get started.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-4 mb-md-0">
                <div class="card h-100 border-0 shadow-sm text-center">
                    <div class="card-body p-4">
                        <div class="step-icon bg-primary text-white rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                            <i class="fas fa-search fa-2x"></i>
                        </div>
                        <h4 class="mb-3">Find Opportunities</h4>
                        <p class="text-muted">Browse job listings or post new positions. Use filters to find the perfect match for your skills or hiring needs.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm text-center">
                    <div class="card-body p-4">
                        <div class="step-icon bg-primary text-white rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                            <i class="fas fa-handshake fa-2x"></i>
                        </div>
                        <h4 class="mb-3">Connect & Succeed</h4>
                        <p class="text-muted">Apply for jobs or review applicants. Communication tools help both parties find the right fit efficiently.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="text-center mt-4">
            <a href="auth/register.php" class="btn btn-primary btn-lg">
                <i class="fas fa-rocket me-2"></i> Get Started Today
            </a>
        </div>
    </div>
</section>

<!-- Testimonials Section -->
<section class="testimonials py-5 bg-light mb-5">
    <div class="container">
        <h2 class="section-title text-center mb-5">What Our Users Say</h2>
        
        <div class="row">
            <div class="col-md-4 mb-4 mb-md-0">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-4">
                            <img src="https://images.pexels.com/photos/2381069/pexels-photo-2381069.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=2" alt="User" class="rounded-circle me-3" width="60">
                            <div>
                                <h5 class="mb-1">Sarah Johnson</h5>
                                <p class="text-muted mb-0">Software Developer</p>
                            </div>
                        </div>
                        <p class="mb-0">"I found my dream job through JobConnect within just two weeks of signing up! The platform is intuitive and the job matching algorithm really works. Highly recommended for tech professionals."</p>
                    </div>
                    <div class="card-footer bg-white border-top-0 text-end">
                        <div class="text-warning">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-4 mb-md-0">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-4">
                            <img src="https://images.pexels.com/photos/2379004/pexels-photo-2379004.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=2" alt="User" class="rounded-circle me-3" width="60">
                            <div>
                                <h5 class="mb-1">Michael Rodriguez</h5>
                                <p class="text-muted mb-0">HR Manager, TechCorp</p>
                            </div>
                        </div>
                        <p class="mb-0">"As an employer, JobConnect has transformed our hiring process. The quality of candidates is exceptional, and the platform's features make it easy to find the right talent. We've filled multiple positions quickly."</p>
                    </div>
                    <div class="card-footer bg-white border-top-0 text-end">
                        <div class="text-warning">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star-half-alt"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-4">
                            <img src="https://images.pexels.com/photos/2613260/pexels-photo-2613260.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=2" alt="User" class="rounded-circle me-3" width="60">
                            <div>
                                <h5 class="mb-1">Emily Chen</h5>
                                <p class="text-muted mb-0">Marketing Specialist</p>
                            </div>
                        </div>
                        <p class="mb-0">"After struggling with other job portals, JobConnect was a breath of fresh air. The user interface is clean, applications are straightforward, and I received responses quickly. I'm now happily employed!"</p>
                    </div>
                    <div class="card-footer bg-white border-top-0 text-end">
                        <div class="text-warning">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Call to Action Section -->
<section class="cta mb-5">
    <div class="container">
        <div class="row">
            <div class="col-md-6 mb-4 mb-md-0">
                <div class="card bg-primary text-white border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h3 class="card-title">For Job Seekers</h3>
                        <p class="card-text mb-4">Create a profile, upload your resume, and start applying to jobs that match your skills and career goals.</p>
                        <a href="auth/register.php?type=jobseeker" class="btn btn-light text-primary">
                            <i class="fas fa-user-tie me-2"></i> Register as Job Seeker
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card bg-success text-white border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h3 class="card-title">For Employers</h3>
                        <p class="card-text mb-4">Post job openings, browse candidates, and find the perfect talent to join your team quickly and efficiently.</p>
                        <a href="auth/register.php?type=employer" class="btn btn-light text-success">
                            <i class="fas fa-building me-2"></i> Register as Employer
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
// Include footer
include_once 'includes/footer.php';
?>