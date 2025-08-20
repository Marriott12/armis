/**
 * ARMIS Training Module JavaScript
 * Dynamic functionality for training operations
 */

class TrainingModule {
    constructor() {
        this.apiBaseUrl = '/Armis2/training/api.php';
        this.refreshInterval = 30000; // 30 seconds
        this.init();
    }
    
    init() {
        this.setupEventListeners();
        this.startRealTimeUpdates();
        this.loadDashboardData();
    }
    
    setupEventListeners() {
        // Course form submission
        document.addEventListener('submit', (e) => {
            if (e.target.id === 'courseForm') {
                e.preventDefault();
                this.submitCourseForm(e.target);
            }
        });
        
        // Enrollment form submission
        document.addEventListener('submit', (e) => {
            if (e.target.id === 'enrollmentForm') {
                e.preventDefault();
                this.submitEnrollmentForm(e.target);
            }
        });
        
        // Training completion
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('complete-training-btn')) {
                this.completeTraining(e.target);
            }
        });
        
        // Certification issuance
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('issue-cert-btn')) {
                this.issueCertification(e.target);
            }
        });
        
        // Real-time refresh
        document.addEventListener('click', (e) => {
            if (e.target.id === 'refreshTrainingDashboard') {
                this.loadDashboardData();
            }
        });
        
        // Course search
        const courseSearchInput = document.getElementById('courseSearch');
        if (courseSearchInput) {
            courseSearchInput.addEventListener('input', this.debounce(() => {
                this.searchCourses(courseSearchInput.value);
            }, 300));
        }
        
        // Course filters
        document.addEventListener('change', (e) => {
            if (e.target.classList.contains('course-filter')) {
                this.filterCourses();
            }
        });
    }
    
    async loadDashboardData() {
        try {
            this.showLoading('training-dashboard-stats');
            
            const response = await fetch(`${this.apiBaseUrl}?endpoint=dashboard`);
            const data = await response.json();
            
            if (data.success) {
                this.updateDashboardStats(data.data.stats);
                this.updateUpcomingTraining(data.data.upcoming_training);
                this.updateActiveCourses(data.data.active_courses);
                this.updateRecentCompletions(data.data.recent_completions);
            }
            
            this.hideLoading('training-dashboard-stats');
        } catch (error) {
            console.error('Failed to load training dashboard data:', error);
            this.showError('Failed to load dashboard data');
        }
    }
    
    updateDashboardStats(stats) {
        const elements = {
            'active-courses-count': stats.active_courses,
            'completion-rate': stats.completion_rate + '%',
            'pending-certs-count': stats.pending_certs,
            'instructors-count': stats.instructors
        };
        
        Object.entries(elements).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = value;
            }
        });
    }
    
    updateUpcomingTraining(training) {
        const container = document.getElementById('upcoming-training-list');
        if (!container) return;
        
        if (training.length === 0) {
            container.innerHTML = '<p class="text-muted">No upcoming training scheduled</p>';
            return;
        }
        
        container.innerHTML = training.map(session => `
            <div class="training-session-item mb-2 p-3 border rounded">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1">${this.escapeHtml(session.course_name)}</h6>
                        <small class="text-muted">
                            Instructor: ${this.escapeHtml(session.instructor_name || 'TBD')}
                        </small>
                    </div>
                    <div class="text-end">
                        <div class="badge bg-info">${this.formatDate(session.start_date)}</div>
                    </div>
                </div>
            </div>
        `).join('');
    }
    
    updateActiveCourses(courses) {
        const container = document.getElementById('active-courses-list');
        if (!container) return;
        
        container.innerHTML = courses.slice(0, 5).map(course => `
            <div class="course-item mb-2 p-2 border rounded">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1">${this.escapeHtml(course.course_name)}</h6>
                        <small class="text-muted">
                            ${this.escapeHtml(course.category)} • ${course.duration_hours}h
                        </small>
                    </div>
                    <span class="badge bg-${this.getDifficultyClass(course.difficulty)}">
                        ${course.difficulty}
                    </span>
                </div>
            </div>
        `).join('');
    }
    
    updateRecentCompletions(completions) {
        const container = document.getElementById('recent-completions-list');
        if (!container) return;
        
        if (completions.length === 0) {
            container.innerHTML = '<p class="text-muted">No recent completions</p>';
            return;
        }
        
        container.innerHTML = completions.map(completion => `
            <div class="completion-item mb-2 p-2 border-left border-success">
                <div class="d-flex justify-content-between">
                    <div>
                        <strong>${this.escapeHtml(completion.course_name)}</strong><br>
                        <small>${this.escapeHtml(completion.fname)} ${this.escapeHtml(completion.lname)} (${this.escapeHtml(completion.svcno)})</small>
                    </div>
                    <small class="text-muted">${this.formatDate(completion.completion_date)}</small>
                </div>
            </div>
        `).join('');
    }
    
    async submitCourseForm(form) {
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        
        try {
            const response = await fetch(`${this.apiBaseUrl}?endpoint=courses`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showSuccess('Course created successfully');
                form.reset();
                this.loadDashboardData();
            } else {
                this.showError(result.error || 'Failed to create course');
            }
        } catch (error) {
            console.error('Error submitting course:', error);
            this.showError('Failed to create course');
        }
    }
    
    async submitEnrollmentForm(form) {
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        
        try {
            const response = await fetch(`${this.apiBaseUrl}?endpoint=enroll`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showSuccess('Staff enrolled successfully');
                form.reset();
            } else {
                this.showError(result.error || 'Failed to enroll staff');
            }
        } catch (error) {
            console.error('Error enrolling staff:', error);
            this.showError('Failed to enroll staff');
        }
    }
    
    async completeTraining(button) {
        const recordId = button.dataset.recordId;
        const score = prompt('Enter training score (optional):');
        
        const data = {
            record_id: recordId,
            action: 'complete'
        };
        
        if (score !== null && score.trim() !== '') {
            data.score = parseFloat(score);
        }
        
        try {
            const response = await fetch(`${this.apiBaseUrl}?endpoint=training-records`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showSuccess('Training completed successfully');
                button.disabled = true;
                button.textContent = 'Completed';
                button.classList.remove('btn-primary');
                button.classList.add('btn-success');
            } else {
                this.showError(result.error || 'Failed to complete training');
            }
        } catch (error) {
            console.error('Error completing training:', error);
            this.showError('Failed to complete training');
        }
    }
    
    async issueCertification(button) {
        const staffId = button.dataset.staffId;
        const certType = prompt('Enter certification type:');
        
        if (!certType) return;
        
        const validityMonths = prompt('Enter validity period (months):', '24');
        
        const data = {
            staff_id: staffId,
            certification_type: certType,
            validity_months: parseInt(validityMonths) || 24
        };
        
        try {
            const response = await fetch(`${this.apiBaseUrl}?endpoint=certifications`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showSuccess('Certification issued successfully');
            } else {
                this.showError(result.error || 'Failed to issue certification');
            }
        } catch (error) {
            console.error('Error issuing certification:', error);
            this.showError('Failed to issue certification');
        }
    }
    
    async searchCourses(query) {
        try {
            const response = await fetch(`${this.apiBaseUrl}?endpoint=courses&search=${encodeURIComponent(query)}`);
            const data = await response.json();
            
            if (data.success) {
                this.displayCourseSearchResults(data.data);
            }
        } catch (error) {
            console.error('Course search error:', error);
        }
    }
    
    async filterCourses() {
        const filters = {};
        document.querySelectorAll('.course-filter').forEach(filter => {
            if (filter.value) {
                filters[filter.name] = filter.value;
            }
        });
        
        try {
            const params = new URLSearchParams(filters);
            params.append('endpoint', 'courses');
            
            const response = await fetch(`${this.apiBaseUrl}?${params}`);
            const data = await response.json();
            
            if (data.success) {
                this.displayCourseSearchResults(data.data);
            }
        } catch (error) {
            console.error('Course filter error:', error);
        }
    }
    
    displayCourseSearchResults(courses) {
        const container = document.getElementById('course-search-results');
        if (!container) return;
        
        if (courses.length === 0) {
            container.innerHTML = '<p class="text-muted">No courses found</p>';
            return;
        }
        
        container.innerHTML = courses.map(course => `
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h5 class="card-title">${this.escapeHtml(course.course_name)}</h5>
                            <p class="card-text">${this.escapeHtml(course.description)}</p>
                            <div class="d-flex gap-2 flex-wrap">
                                <span class="badge bg-info">${course.category}</span>
                                <span class="badge bg-${this.getDifficultyClass(course.difficulty)}">
                                    ${course.difficulty}
                                </span>
                                <span class="badge bg-secondary">${course.duration_hours}h</span>
                            </div>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-${this.getStatusClass(course.status)} mb-2">
                                ${course.status}
                            </span>
                            <br>
                            <button class="btn btn-sm btn-primary" onclick="window.location.href='/Armis2/training/course.php?id=${course.id}'">
                                View Details
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
    }
    
    startRealTimeUpdates() {
        setInterval(() => {
            this.loadDashboardData();
        }, this.refreshInterval);
    }
    
    // Utility functions
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    formatDate(dateString) {
        return new Date(dateString).toLocaleDateString();
    }
    
    getDifficultyClass(difficulty) {
        const classes = {
            'Beginner': 'success',
            'Intermediate': 'info',
            'Advanced': 'warning',
            'Expert': 'danger'
        };
        return classes[difficulty] || 'secondary';
    }
    
    getStatusClass(status) {
        const classes = {
            'Draft': 'secondary',
            'Active': 'success',
            'Suspended': 'warning',
            'Completed': 'primary',
            'Cancelled': 'danger'
        };
        return classes[status] || 'secondary';
    }
    
    showLoading(elementId) {
        const element = document.getElementById(elementId);
        if (element) {
            element.style.opacity = '0.5';
        }
    }
    
    hideLoading(elementId) {
        const element = document.getElementById(elementId);
        if (element) {
            element.style.opacity = '1';
        }
    }
    
    showSuccess(message) {
        this.showToast(message, 'success');
    }
    
    showError(message) {
        this.showToast(message, 'danger');
    }
    
    showToast(message, type) {
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        toast.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 5000);
    }
    
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.trainingModule = new TrainingModule();
});