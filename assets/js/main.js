/**
 * Main JavaScript file for JobConnect Portal
 */
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize Bootstrap popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
    
    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        var alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
    
    // Job filter form handling
    const filterForm = document.getElementById('job-filter-form');
    if (filterForm) {
        // Save filter preferences to localStorage
        filterForm.addEventListener('submit', function() {
            const formData = new FormData(filterForm);
            const filters = {};
            
            for (const [key, value] of formData.entries()) {
                if (value) {
                    filters[key] = value;
                }
            }
            
            localStorage.setItem('jobFilters', JSON.stringify(filters));
        });
        
        // Load saved filters
        const savedFilters = localStorage.getItem('jobFilters');
        if (savedFilters) {
            const filters = JSON.parse(savedFilters);
            
            for (const [key, value] of Object.entries(filters)) {
                const field = filterForm.querySelector(`[name="${key}"]`);
                if (field) {
                    field.value = value;
                }
            }
        }
        
        // Clear filters button
        const clearFiltersBtn = document.getElementById('clear-filters');
        if (clearFiltersBtn) {
            clearFiltersBtn.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Reset form fields
                filterForm.reset();
                
                // Clear saved filters
                localStorage.removeItem('jobFilters');
                
                // Submit the form to refresh results
                filterForm.submit();
            });
        }
    }
    
    // Job application form validation
    const applicationForm = document.getElementById('job-application-form');
    if (applicationForm) {
        applicationForm.addEventListener('submit', function(e) {
            const resumeInput = document.getElementById('resume');
            
            if (resumeInput && resumeInput.files.length === 0 && !document.getElementById('existing-resume')) {
                e.preventDefault();
                
                // Create error message if it doesn't exist
                let errorMsg = document.getElementById('resume-error');
                if (!errorMsg) {
                    errorMsg = document.createElement('div');
                    errorMsg.id = 'resume-error';
                    errorMsg.className = 'invalid-feedback d-block';
                    errorMsg.textContent = 'Please upload your resume';
                    resumeInput.parentNode.appendChild(errorMsg);
                }
                
                // Highlight the input
                resumeInput.classList.add('is-invalid');
            }
        });
    }
    
    // Password strength meter
    const passwordInput = document.getElementById('password');
    const passwordStrength = document.getElementById('password-strength');
    
    if (passwordInput && passwordStrength) {
        passwordInput.addEventListener('input', function() {
            const password = passwordInput.value;
            let strength = 0;
            
            // Length check
            if (password.length >= 8) {
                strength += 1;
            }
            
            // Uppercase check
            if (/[A-Z]/.test(password)) {
                strength += 1;
            }
            
            // Lowercase check
            if (/[a-z]/.test(password)) {
                strength += 1;
            }
            
            // Number check
            if (/[0-9]/.test(password)) {
                strength += 1;
            }
            
            // Special character check
            if (/[^A-Za-z0-9]/.test(password)) {
                strength += 1;
            }
            
            // Update strength meter
            passwordStrength.className = 'progress-bar';
            
            if (strength === 0) {
                passwordStrength.style.width = '0%';
                passwordStrength.classList.add('bg-danger');
            } else if (strength <= 2) {
                passwordStrength.style.width = '33%';
                passwordStrength.classList.add('bg-danger');
            } else if (strength <= 4) {
                passwordStrength.style.width = '66%';
                passwordStrength.classList.add('bg-warning');
            } else {
                passwordStrength.style.width = '100%';
                passwordStrength.classList.add('bg-success');
            }
        });
    }
    
    // Animated counters for statistics
    const statCounters = document.querySelectorAll('.stat-counter');
    
    function animateCounter(counter, target, duration) {
        let start = 0;
        const increment = target / (duration / 16);
        
        function updateCounter() {
            start += increment;
            const current = Math.min(Math.floor(start), target);
            counter.textContent = current.toLocaleString();
            
            if (current < target) {
                requestAnimationFrame(updateCounter);
            }
        }
        
        updateCounter();
    }
    
    // Use Intersection Observer to trigger counter animations when visible
    if (statCounters.length > 0) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const target = parseInt(entry.target.getAttribute('data-target'), 10);
                    animateCounter(entry.target, target, 1000);
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });
        
        statCounters.forEach(counter => {
            observer.observe(counter);
        });
    }
    
    // Confirmation dialogs
    const confirmForms = document.querySelectorAll('.confirm-action');
    confirmForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const message = form.getAttribute('data-confirm') || 'Are you sure you want to proceed?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
    
    // Job search autocomplete for location
    const locationInput = document.getElementById('location');
    if (locationInput) {
        // This is a simplified example - in production, you'd connect to a real location API
        const popularLocations = [
            'New York, NY', 'Los Angeles, CA', 'Chicago, IL', 'Houston, TX', 
            'Phoenix, AZ', 'Philadelphia, PA', 'San Antonio, TX', 'San Diego, CA',
            'Dallas, TX', 'San Jose, CA', 'Austin, TX', 'Jacksonville, FL',
            'San Francisco, CA', 'Columbus, OH', 'Indianapolis, IN', 'Seattle, WA'
        ];
        
        // Create and append datalist
        const datalist = document.createElement('datalist');
        datalist.id = 'location-suggestions';
        
        popularLocations.forEach(location => {
            const option = document.createElement('option');
            option.value = location;
            datalist.appendChild(option);
        });
        
        document.body.appendChild(datalist);
        locationInput.setAttribute('list', 'location-suggestions');
    }
});