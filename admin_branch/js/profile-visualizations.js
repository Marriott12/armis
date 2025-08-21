/**
 * ARMIS Profile Visualization Components
 * This file contains visualization functions for the staff profile
 */

// Initialize visualizations when document is ready
document.addEventListener('DOMContentLoaded', function() {
    initVisualTimeline();
    initServiceStats();
    initMedalDisplay();
    initRankChart();
});

/**
 * Create visual timeline from timeline data
 */
function initVisualTimeline() {
    const timelineContainer = document.getElementById('visualTimeline');
    if (!timelineContainer) return;
    
    // Get all timeline events from the DOM
    const timelineItems = document.querySelectorAll('.timeline-item');
    if (timelineItems.length === 0) return;
    
    // Create the timeline visualization
    const timelineVisual = document.createElement('div');
    timelineVisual.className = 'timeline-visual';
    
    // Create timeline line
    const timelineLine = document.createElement('div');
    timelineLine.className = 'timeline-line';
    timelineVisual.appendChild(timelineLine);
    
    // Get date range for scaling
    let earliestDate = new Date();
    let latestDate = new Date(0);
    
    timelineItems.forEach(item => {
        const dateText = item.querySelector('.timeline-date').textContent.trim();
        const date = new Date(dateText);
        
        if (!isNaN(date.getTime())) {
            if (date < earliestDate) earliestDate = date;
            if (date > latestDate) latestDate = date;
        }
    });
    
    // If we only have one date, set the range to 1 year for scaling
    if (earliestDate.getTime() === latestDate.getTime()) {
        earliestDate = new Date(earliestDate);
        earliestDate.setFullYear(earliestDate.getFullYear() - 1);
    }
    
    const timeRange = latestDate.getTime() - earliestDate.getTime();
    
    // Add timeline points
    timelineItems.forEach(item => {
        const dateText = item.querySelector('.timeline-date').textContent.trim();
        const date = new Date(dateText);
        const type = item.getAttribute('data-type');
        const title = item.querySelector('.timeline-title').textContent.trim();
        
        if (!isNaN(date.getTime())) {
            // Calculate position as percentage
            const position = ((date.getTime() - earliestDate.getTime()) / timeRange) * 100;
            
            // Create the timeline point
            const point = document.createElement('div');
            point.className = `timeline-point ${type}`;
            point.style.left = `${position}%`;
            point.setAttribute('title', `${title} (${dateText})`);
            point.setAttribute('data-bs-toggle', 'tooltip');
            
            // Add icon based on type
            let icon = 'fa-star';
            switch (type) {
                case 'promotion': icon = 'fa-arrow-up'; break;
                case 'medal': icon = 'fa-medal'; break;
                case 'course': icon = 'fa-graduation-cap'; break;
                case 'enlist': icon = 'fa-user-plus'; break;
            }
            
            point.innerHTML = `<i class="fa ${icon}"></i>`;
            
            // Add click handler to show details
            point.addEventListener('click', () => {
                // Highlight corresponding item in the text timeline
                document.querySelectorAll('.timeline-item').forEach(i => i.classList.remove('highlight'));
                item.classList.add('highlight');
                item.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
            
            timelineVisual.appendChild(point);
        }
    });
    
    // Add date labels
    const yearRange = latestDate.getFullYear() - earliestDate.getFullYear();
    const labelStep = yearRange <= 5 ? 1 : Math.ceil(yearRange / 5);
    
    for (let year = earliestDate.getFullYear(); year <= latestDate.getFullYear(); year += labelStep) {
        const labelDate = new Date(year, 0, 1);
        const position = ((labelDate.getTime() - earliestDate.getTime()) / timeRange) * 100;
        
        // Only add label if it's within the timeline range
        if (position >= 0 && position <= 100) {
            const label = document.createElement('div');
            label.className = 'timeline-label';
            label.style.left = `${position}%`;
            label.textContent = year;
            timelineVisual.appendChild(label);
        }
    }
    
    timelineContainer.appendChild(timelineVisual);
    
    // Initialize tooltips if Bootstrap is available
    if (typeof bootstrap !== 'undefined') {
        const tooltips = timelineContainer.querySelectorAll('[data-bs-toggle="tooltip"]');
        tooltips.forEach(tooltip => {
            new bootstrap.Tooltip(tooltip);
        });
    }
}

/**
 * Create service statistics visualization
 */
function initServiceStats() {
    const statsContainer = document.getElementById('serviceStatsChart');
    if (!statsContainer || typeof Chart === 'undefined') return;
    
    // Get stats data
    const yearsOfService = parseInt(document.getElementById('yearsOfService')?.textContent || '0');
    const medalCount = parseInt(document.getElementById('medalCount')?.textContent || '0');
    const promotionCount = parseInt(document.getElementById('promotionCount')?.textContent || '0');
    const courseCount = parseInt(document.getElementById('courseCount')?.textContent || '0');
    
    // Create radar chart
    new Chart(statsContainer, {
        type: 'radar',
        data: {
            labels: [
                'Years of Service', 
                'Medals & Awards',
                'Promotions',
                'Qualifications'
            ],
            datasets: [{
                label: 'Service Statistics',
                data: [
                    yearsOfService,
                    medalCount,
                    promotionCount,
                    courseCount
                ],
                fill: true,
                backgroundColor: 'rgba(0, 123, 255, 0.2)',
                borderColor: 'rgb(0, 123, 255)',
                pointBackgroundColor: 'rgb(0, 123, 255)',
                pointBorderColor: '#fff',
                pointHoverBackgroundColor: '#fff',
                pointHoverBorderColor: 'rgb(0, 123, 255)'
            }]
        },
        options: {
            scales: {
                r: {
                    angleLines: {
                        display: true
                    },
                    suggestedMin: 0
                }
            }
        }
    });
}

/**
 * Create medal display visualization
 */
function initMedalDisplay() {
    const medalContainer = document.getElementById('medalVisualDisplay');
    if (!medalContainer) return;
    
    // Group medals by type
    const medals = {};
    document.querySelectorAll('.medal-item').forEach(medal => {
        const medalName = medal.getAttribute('data-medal-name');
        medals[medalName] = (medals[medalName] || 0) + 1;
    });
    
    // Create visual medal display
    Object.keys(medals).forEach(medalName => {
        const medalCount = medals[medalName];
        
        const medalDiv = document.createElement('div');
        medalDiv.className = 'medal-icon';
        medalDiv.innerHTML = `
            <i class="fa fa-medal"></i>
            <span class="medal-count">${medalCount}</span>
            <div class="medal-tooltip">${medalName}</div>
        `;
        
        medalContainer.appendChild(medalDiv);
    });
}

/**
 * Create rank progression chart
 */
function initRankChart() {
    const rankChartContainer = document.getElementById('rankProgressionChart');
    if (!rankChartContainer || typeof Chart === 'undefined') return;
    
    // Get promotion timeline data
    const promotions = [];
    document.querySelectorAll('.timeline-item[data-type="promotion"]').forEach(item => {
        const rankName = item.querySelector('.timeline-title').textContent.replace('Promoted to ', '').trim();
        const dateText = item.querySelector('.timeline-date').textContent.trim();
        
        promotions.push({
            rank: rankName,
            date: new Date(dateText)
        });
    });
    
    // Add enlistment as first rank
    const enlistment = document.querySelector('.timeline-item[data-type="enlist"]');
    if (enlistment) {
        const rankName = document.querySelector('.profile-rank').textContent.trim();
        const dateText = enlistment.querySelector('.timeline-date').textContent.trim();
        
        promotions.push({
            rank: 'Initial Rank',
            date: new Date(dateText)
        });
    }
    
    // Sort by date
    promotions.sort((a, b) => a.date - b.date);
    
    // Create chart
    new Chart(rankChartContainer, {
        type: 'line',
        data: {
            labels: promotions.map(p => p.date.toLocaleDateString()),
            datasets: [{
                label: 'Rank Progression',
                data: promotions.map((p, index) => index + 1),
                steppedLine: true,
                fill: false,
                borderColor: 'rgb(40, 167, 69)',
                tension: 0
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
                        text: 'Rank Level'
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const index = context.dataIndex;
                            return promotions[index].rank;
                        }
                    }
                }
            }
        }
    });
}
