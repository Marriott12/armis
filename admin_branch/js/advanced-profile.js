/**
 * ARMIS Advanced Staff Profile
 * This file contains all the JavaScript functionality for the enhanced staff profile view
 */

// Initialize all components when document is ready
document.addEventListener('DOMContentLoaded', function() {
    initializeTabSystem();
    initializeCharts();
    initializeTimeline();
    initializeEditableFields();
    initializeTooltips();
    setupPrintHandler();
    setupExportHandlers();
    calculateServiceStats();
});

/**
 * Tab navigation system
 */
function initializeTabSystem() {
    const tabLinks = document.querySelectorAll('.profile-tabs .nav-link');
    const tabContents = document.querySelectorAll('.profile-tab-content');
    
    // Set the first tab as active by default
    if (tabLinks.length > 0 && !document.querySelector('.profile-tabs .nav-link.active')) {
        tabLinks[0].classList.add('active');
        const targetId = tabLinks[0].getAttribute('data-bs-target');
        document.querySelector(targetId).classList.add('show', 'active');
    }
    
    // Handle tab clicks
    tabLinks.forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Remove active class from all tabs and hide all tab contents
            tabLinks.forEach(t => t.classList.remove('active'));
            tabContents.forEach(c => {
                c.classList.remove('show', 'active');
            });
            
            // Activate the clicked tab and show its content
            this.classList.add('active');
            const targetId = this.getAttribute('data-bs-target');
            document.querySelector(targetId).classList.add('show', 'active');
            
            // Save the active tab to session storage
            sessionStorage.setItem('activeProfileTab', targetId);
        });
    });
    
    // Restore active tab from session storage if available
    const savedTab = sessionStorage.getItem('activeProfileTab');
    if (savedTab) {
        const tab = document.querySelector(`.profile-tabs .nav-link[data-bs-target="${savedTab}"]`);
        if (tab) {
            tab.click();
        }
    }
}

/**
 * Initialize charts and visualizations
 */
function initializeCharts() {
    // Check if Chart.js is loaded
    if (typeof Chart === 'undefined') {
        console.warn('Chart.js not loaded. Skipping chart initialization.');
        return;
    }
    
    // Service Timeline Chart
    const timelineCtx = document.getElementById('serviceTimelineChart');
    if (timelineCtx) {
        // Collect data from the timeline
        const timelineItems = document.querySelectorAll('.timeline-item');
        const labels = [];
        const data = [];
        
        timelineItems.forEach((item, index) => {
            const date = item.querySelector('.timeline-date').textContent.trim();
            labels.push(date);
            data.push(index + 1);
        });
        
        new Chart(timelineCtx, {
            type: 'line',
            data: {
                labels: labels.reverse(),
                datasets: [{
                    label: 'Career Progression',
                    data: data.reverse(),
                    borderColor: '#007bff',
                    tension: 0.1,
                    fill: false
                }]
            },
            options: {
                responsive: true,
                scales: {
                    x: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Date'
                        }
                    },
                    y: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Progression'
                        }
                    }
                }
            }
        });
    }
    
    // Qualifications Chart
    const qualificationsCtx = document.getElementById('qualificationsChart');
    if (qualificationsCtx) {
        // Extract qualification categories
        const categories = {};
        document.querySelectorAll('.qualification-item').forEach(item => {
            const category = item.getAttribute('data-category') || 'Other';
            categories[category] = (categories[category] || 0) + 1;
        });
        
        new Chart(qualificationsCtx, {
            type: 'doughnut',
            data: {
                labels: Object.keys(categories),
                datasets: [{
                    data: Object.values(categories),
                    backgroundColor: [
                        '#007bff', '#28a745', '#ffc107', '#17a2b8', '#6c757d', '#dc3545'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right',
                    }
                }
            }
        });
    }
}

/**
 * Enhanced Timeline Functionality
 */
function initializeTimeline() {
    // Add interactive elements to timeline
    document.querySelectorAll('.timeline-item').forEach(item => {
        item.addEventListener('click', function() {
            this.querySelector('.timeline-content').classList.toggle('expanded');
        });
        
        // Add icons to timeline badges based on type
        const badgeType = item.getAttribute('data-type');
        const badge = item.querySelector('.timeline-badge');
        
        if (badge) {
            let icon = 'fa-star';
            
            switch (badgeType) {
                case 'promotion':
                    icon = 'fa-arrow-up';
                    break;
                case 'medal':
                    icon = 'fa-medal';
                    break;
                case 'course':
                    icon = 'fa-graduation-cap';
                    break;
                case 'enlist':
                    icon = 'fa-user-plus';
                    break;
            }
            
            const iconElement = document.createElement('i');
            iconElement.className = `fa ${icon} fa-sm`;
            badge.appendChild(iconElement);
        }
    });
}

/**
 * In-place editable fields
 */
function initializeEditableFields() {
    // Add edit triggers to editable fields
    document.querySelectorAll('.editable-field').forEach(field => {
        // Only set up editing if admin access is available
        if (!document.body.classList.contains('admin-access')) {
            return;
        }
        
        const editTrigger = document.createElement('span');
        editTrigger.className = 'edit-trigger';
        editTrigger.innerHTML = '<i class="fa fa-edit"></i>';
        field.appendChild(editTrigger);
        
        editTrigger.addEventListener('click', function() {
            const currentValue = field.getAttribute('data-value') || field.textContent.trim();
            const fieldName = field.getAttribute('data-field');
            const fieldType = field.getAttribute('data-type') || 'text';
            const staffId = field.closest('[data-staff-id]').getAttribute('data-staff-id');
            
            // Create the edit form
            const form = document.createElement('form');
            form.className = 'edit-form';
            form.innerHTML = `
                <div class="input-group input-group-sm">
                    <input type="${fieldType}" class="form-control" name="${fieldName}" value="${currentValue}">
                    <button type="submit" class="btn btn-sm btn-primary"><i class="fa fa-save"></i></button>
                    <button type="button" class="btn btn-sm btn-secondary cancel-edit"><i class="fa fa-times"></i></button>
                </div>
            `;
            
            // Replace the content with the form
            const originalContent = field.innerHTML;
            field.innerHTML = '';
            field.appendChild(form);
            
            // Focus the input
            form.querySelector('input').focus();
            
            // Handle form submission
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const newValue = this.querySelector('input').value;
                
                // Show loading state
                field.innerHTML = '<div class="spinner-border spinner-border-sm text-secondary" role="status"><span class="visually-hidden">Loading...</span></div>';
                
                // Simulate AJAX request (would be replaced with actual AJAX in production)
                setTimeout(() => {
                    field.textContent = newValue;
                    field.setAttribute('data-value', newValue);
                    field.appendChild(editTrigger);
                    
                    // Show success message
                    const toast = document.createElement('div');
                    toast.className = 'toast align-items-center text-white bg-success border-0 position-fixed bottom-0 end-0 m-3';
                    toast.setAttribute('role', 'alert');
                    toast.setAttribute('aria-live', 'assertive');
                    toast.setAttribute('aria-atomic', 'true');
                    toast.innerHTML = `
                        <div class="d-flex">
                            <div class="toast-body">
                                Field updated successfully!
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                        </div>
                    `;
                    document.body.appendChild(toast);
                    
                    // Initialize the toast via Bootstrap's API
                    if (typeof bootstrap !== 'undefined') {
                        new bootstrap.Toast(toast).show();
                    }
                    
                    // Remove the toast after 3 seconds
                    setTimeout(() => {
                        toast.remove();
                    }, 3000);
                }, 500);
            });
            
            // Handle cancel button
            form.querySelector('.cancel-edit').addEventListener('click', function() {
                field.innerHTML = originalContent;
            });
        });
    });
}

/**
 * Initialize tooltips and popovers
 */
function initializeTooltips() {
    // Set up tooltips if Bootstrap is available
    if (typeof bootstrap !== 'undefined') {
        const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        tooltips.forEach(tooltip => {
            new bootstrap.Tooltip(tooltip);
        });
        
        const popovers = document.querySelectorAll('[data-bs-toggle="popover"]');
        popovers.forEach(popover => {
            new bootstrap.Popover(popover);
        });
    }
    
    // Custom tooltips for medals
    document.querySelectorAll('.medal-icon').forEach(medal => {
        medal.addEventListener('mouseenter', function() {
            const tooltip = this.querySelector('.medal-tooltip');
            if (tooltip) {
                tooltip.style.visibility = 'visible';
                tooltip.style.opacity = '1';
            }
        });
        
        medal.addEventListener('mouseleave', function() {
            const tooltip = this.querySelector('.medal-tooltip');
            if (tooltip) {
                tooltip.style.visibility = 'hidden';
                tooltip.style.opacity = '0';
            }
        });
    });
}

/**
 * Set up print handler with custom formatting
 */
function setupPrintHandler() {
    document.querySelectorAll('.print-profile').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Custom print preparation
            document.body.classList.add('printing');
            
            // Show all tabs in print view
            document.querySelectorAll('.profile-tab-content').forEach(tab => {
                tab.classList.add('print-visible');
            });
            
            window.print();
            
            // Restore after print dialog closes
            setTimeout(() => {
                document.body.classList.remove('printing');
                document.querySelectorAll('.profile-tab-content').forEach(tab => {
                    tab.classList.remove('print-visible');
                });
            }, 1000);
        });
    });
}

/**
 * Export data in various formats
 */
function setupExportHandlers() {
    // CSV Export (already implemented in your code)
    
    // PDF Export if jsPDF is available
    document.querySelectorAll('.export-pdf').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Check if jsPDF is loaded
            if (typeof jsPDF === 'undefined') {
                alert('PDF export library not loaded. Please try CSV export instead.');
                return;
            }
            
            const staffName = document.querySelector('.profile-name').textContent;
            const serviceNumber = document.querySelector('.service-number').textContent;
            
            // Create PDF document
            const doc = new jsPDF();
            doc.setFontSize(18);
            doc.text(`Staff Profile: ${staffName}`, 20, 20);
            doc.setFontSize(12);
            doc.text(`Service Number: ${serviceNumber}`, 20, 30);
            doc.text(`Generated on: ${new Date().toLocaleString()}`, 20, 40);
            
            // Add more content based on profile data
            // ...
            
            // Save the PDF
            doc.save(`profile_${serviceNumber}.pdf`);
        });
    });
}

/**
 * Calculate service statistics
 */
function calculateServiceStats() {
    const statsContainer = document.getElementById('serviceStats');
    if (!statsContainer) return;
    
    const enrollmentDate = statsContainer.getAttribute('data-enrollment');
    if (!enrollmentDate) return;
    
    // Calculate years of service
    const enrollmentTime = new Date(enrollmentDate).getTime();
    const currentTime = new Date().getTime();
    const yearsOfService = Math.floor((currentTime - enrollmentTime) / (1000 * 60 * 60 * 24 * 365.25));
    
    // Count medals, promotions, etc.
    const medalCount = document.querySelectorAll('.medal-item').length;
    const promotionCount = document.querySelectorAll('.timeline-item[data-type="promotion"]').length;
    const courseCount = document.querySelectorAll('.qualification-item').length;
    
    // Update the stats display
    document.getElementById('yearsOfService').textContent = yearsOfService;
    document.getElementById('medalCount').textContent = medalCount;
    document.getElementById('promotionCount').textContent = promotionCount;
    document.getElementById('courseCount').textContent = courseCount;
}

/**
 * Handle image fallbacks
 */
function handleImageError(img) {
    // Try alternative URLs in sequence
    if (img.dataset.altSrc1 && !img.triedAlt1) {
        img.triedAlt1 = true;
        img.src = img.dataset.altSrc1;
        console.log("Trying alternate image source 1: " + img.src);
        return;
    }
    
    if (img.dataset.altSrc2 && !img.triedAlt2) {
        img.triedAlt2 = true;
        img.src = img.dataset.altSrc2;
        console.log("Trying alternate image source 2: " + img.src);
        return;
    }
    
    if (img.dataset.altSrc3 && !img.triedAlt3) {
        img.triedAlt3 = true;
        img.src = img.dataset.altSrc3;
        console.log("Trying alternate image source 3: " + img.src);
        return;
    }
    
    // Final fallback
    img.src = '/Armis2/assets/army-logo.svg';
    img.alt = 'Photo Not Found';
    console.log("Using fallback image: " + img.src);
}
