/**
 * ARMIS Utilities
 * Common utility functions for the ARMIS system
 */

/**
 * Sanitize input to prevent XSS attacks
 * @param {string} input - The input to sanitize
 * @returns {string} Sanitized input
 */
function sanitizeInput(input) {
    if (typeof input !== 'string') return '';
    return input
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

/**
 * Format a date string to a specified format
 * @param {string} dateString - The date string to format
 * @param {string} format - The format to use (default: 'YYYY-MM-DD')
 * @returns {string} Formatted date string
 */
function formatDate(dateString, format = 'YYYY-MM-DD') {
    if (!dateString) return '';
    
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return '';
    
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    
    let result = format;
    result = result.replace('YYYY', year);
    result = result.replace('MM', month);
    result = result.replace('DD', day);
    
    return result;
}

/**
 * Calculate the time difference between two dates
 * @param {string} startDate - The start date
 * @param {string} endDate - The end date (defaults to current date)
 * @returns {Object} Object containing years, months, days
 */
function calculateDateDifference(startDate, endDate = null) {
    if (!startDate) return { years: 0, months: 0, days: 0 };
    
    const start = new Date(startDate);
    const end = endDate ? new Date(endDate) : new Date();
    
    if (isNaN(start.getTime()) || isNaN(end.getTime())) {
        return { years: 0, months: 0, days: 0 };
    }
    
    let years = end.getFullYear() - start.getFullYear();
    let months = end.getMonth() - start.getMonth();
    let days = end.getDate() - start.getDate();
    
    if (days < 0) {
        months -= 1;
        // Get the last day of the previous month
        const lastDayOfPrevMonth = new Date(end.getFullYear(), end.getMonth(), 0).getDate();
        days += lastDayOfPrevMonth;
    }
    
    if (months < 0) {
        years -= 1;
        months += 12;
    }
    
    return { years, months, days };
}

/**
 * Format a date difference object to a human-readable string
 * @param {Object} diff - The date difference object
 * @returns {string} Human-readable string
 */
function formatDateDifference(diff) {
    const { years, months, days } = diff;
    
    let result = [];
    if (years > 0) {
        result.push(`${years} year${years !== 1 ? 's' : ''}`);
    }
    if (months > 0) {
        result.push(`${months} month${months !== 1 ? 's' : ''}`);
    }
    if (days > 0) {
        result.push(`${days} day${days !== 1 ? 's' : ''}`);
    }
    
    if (result.length === 0) {
        return 'Today';
    }
    
    return result.join(', ');
}

/**
 * Calculate the time in service based on attest date
 * @param {string} attestDate - The attest date
 * @returns {string} Formatted time in service
 */
function calculateTimeInService(attestDate) {
    if (!attestDate) return 'N/A';
    
    const diff = calculateDateDifference(attestDate);
    return formatDateDifference(diff);
}

/**
 * Calculate the time in current rank
 * @param {string} dateRanked - The date ranked
 * @returns {string} Formatted time in rank
 */
function calculateTimeInRank(dateRanked) {
    if (!dateRanked) return 'N/A';
    
    const diff = calculateDateDifference(dateRanked);
    return formatDateDifference(diff);
}

/**
 * Generate a secure token
 * @param {number} length - The length of the token
 * @returns {string} Secure token
 */
function generateToken(length = 32) {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    let token = '';
    
    for (let i = 0; i < length; i++) {
        token += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    
    return token;
}

/**
 * Check if a string is a valid date
 * @param {string} dateString - The date string to check
 * @returns {boolean} Whether the string is a valid date
 */
function isValidDate(dateString) {
    if (!dateString) return false;
    
    const date = new Date(dateString);
    return !isNaN(date.getTime());
}

/**
 * Format a number with commas as thousands separators
 * @param {number} number - The number to format
 * @returns {string} Formatted number
 */
function formatNumber(number) {
    if (isNaN(number)) return '0';
    
    return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

/**
 * Format a phone number
 * @param {string} phone - The phone number to format
 * @returns {string} Formatted phone number
 */
function formatPhoneNumber(phone) {
    if (!phone) return '';
    
    // Remove all non-numeric characters
    const cleaned = phone.replace(/\D/g, '');
    
    // Format based on length
    if (cleaned.length === 10) {
        return `(${cleaned.substring(0, 3)}) ${cleaned.substring(3, 6)}-${cleaned.substring(6, 10)}`;
    } else if (cleaned.length === 11) {
        return `+${cleaned.substring(0, 1)} (${cleaned.substring(1, 4)}) ${cleaned.substring(4, 7)}-${cleaned.substring(7, 11)}`;
    }
    
    return phone;
}

/**
 * Generate a slug from a string
 * @param {string} text - The text to convert to a slug
 * @returns {string} Slug
 */
function generateSlug(text) {
    if (!text) return '';
    
    return text
        .toLowerCase()
        .replace(/[^\w ]+/g, '')
        .replace(/ +/g, '-');
}

/**
 * Convert a string to title case
 * @param {string} text - The text to convert
 * @returns {string} Title-cased text
 */
function toTitleCase(text) {
    if (!text) return '';
    
    return text
        .toLowerCase()
        .split(' ')
        .map(word => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

/**
 * Truncate text to a specified length
 * @param {string} text - The text to truncate
 * @param {number} length - The maximum length
 * @param {string} suffix - The suffix to add (default: '...')
 * @returns {string} Truncated text
 */
function truncateText(text, length, suffix = '...') {
    if (!text) return '';
    if (text.length <= length) return text;
    
    return text.substring(0, length).trim() + suffix;
}

/**
 * Check if an email is valid
 * @param {string} email - The email to check
 * @returns {boolean} Whether the email is valid
 */
function isValidEmail(email) {
    if (!email) return false;
    
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return regex.test(email);
}

/**
 * Generate initials from a name
 * @param {string} name - The name to generate initials from
 * @returns {string} Initials
 */
function getInitials(name) {
    if (!name) return '';
    
    return name
        .split(' ')
        .map(part => part.charAt(0))
        .join('')
        .toUpperCase();
}

/**
 * Format a file size in bytes to a human-readable string
 * @param {number} bytes - The size in bytes
 * @param {number} decimals - The number of decimal places (default: 2)
 * @returns {string} Formatted file size
 */
function formatFileSize(bytes, decimals = 2) {
    if (bytes === 0) return '0 Bytes';
    
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
    
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}

/**
 * Debounce a function
 * @param {Function} func - The function to debounce
 * @param {number} wait - The wait time in milliseconds
 * @returns {Function} Debounced function
 */
function debounce(func, wait) {
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

/**
 * Throttle a function
 * @param {Function} func - The function to throttle
 * @param {number} limit - The time limit in milliseconds
 * @returns {Function} Throttled function
 */
function throttle(func, limit) {
    let inThrottle;
    
    return function executedFunction(...args) {
        if (!inThrottle) {
            func(...args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

// Export the utility functions for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        sanitizeInput,
        formatDate,
        calculateDateDifference,
        formatDateDifference,
        calculateTimeInService,
        calculateTimeInRank,
        generateToken,
        isValidDate,
        formatNumber,
        formatPhoneNumber,
        generateSlug,
        toTitleCase,
        truncateText,
        isValidEmail,
        getInitials,
        formatFileSize,
        debounce,
        throttle
    };
} else {
    // For browser environment
    window.ArmisUtils = {
        sanitizeInput,
        formatDate,
        calculateDateDifference,
        formatDateDifference,
        calculateTimeInService,
        calculateTimeInRank,
        generateToken,
        isValidDate,
        formatNumber,
        formatPhoneNumber,
        generateSlug,
        toTitleCase,
        truncateText,
        isValidEmail,
        getInitials,
        formatFileSize,
        debounce,
        throttle
    };
}
