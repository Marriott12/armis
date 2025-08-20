/**
 * ARMIS Messaging Module JavaScript
 * Enhanced functionality for messaging and communication
 */

(function() {
    'use strict';
    
    // Global messaging configuration
    const ARMIS_MESSAGING = {
        config: {
            refreshInterval: 30000, // 30 seconds
            apiBaseUrl: 'api/',
            maxFileSize: 25 * 1024 * 1024, // 25MB
            allowedFileTypes: ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'txt'],
            messageTypes: {
                TEXT: 'text',
                FILE: 'file',
                SYSTEM: 'system'
            },
            priorities: {
                LOW: 'low',
                NORMAL: 'normal',
                HIGH: 'high',
                URGENT: 'urgent'
            }
        },
        
        // State management
        state: {
            currentUser: null,
            activeThreads: [],
            selectedThread: null,
            typingUsers: new Set(),
            unreadCount: 0,
            notifications: [],
            isOnline: true
        },
        
        // WebSocket connection for real-time messaging
        websocket: null,
        
        // Utility functions
        utils: {
            formatDateTime: function(dateString) {
                const date = new Date(dateString);
                const now = new Date();
                const diffTime = Math.abs(now - date);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                
                if (diffDays === 1) {
                    return 'Today at ' + date.toLocaleTimeString('en-US', {
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                } else if (diffDays === 2) {
                    return 'Yesterday at ' + date.toLocaleTimeString('en-US', {
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                } else if (diffDays <= 7) {
                    return date.toLocaleDateString('en-US', {
                        weekday: 'long',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                } else {
                    return date.toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                }
            },
            
            formatFileSize: function(bytes) {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            },
            
            getFileIcon: function(mimeType) {
                if (mimeType.includes('pdf')) return 'file-pdf';
                if (mimeType.includes('word') || mimeType.includes('document')) return 'file-word';
                if (mimeType.includes('excel') || mimeType.includes('spreadsheet')) return 'file-excel';
                if (mimeType.includes('image')) return 'file-image';
                if (mimeType.includes('video')) return 'file-video';
                if (mimeType.includes('audio')) return 'file-audio';
                return 'file-alt';
            },
            
            showToast: function(message, type = 'info', duration = 5000) {
                const toast = document.createElement('div');
                toast.className = `toast align-items-center text-white bg-${type} border-0`;
                toast.setAttribute('role', 'alert');
                toast.innerHTML = `
                    <div class="d-flex">
                        <div class="toast-body">${message}</div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                `;
                
                let toastContainer = document.querySelector('.toast-container');
                if (!toastContainer) {
                    toastContainer = document.createElement('div');
                    toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
                    document.body.appendChild(toastContainer);
                }
                
                toastContainer.appendChild(toast);
                
                const bsToast = new bootstrap.Toast(toast, { delay: duration });
                bsToast.show();
                
                toast.addEventListener('hidden.bs.toast', function() {
                    toast.remove();
                });
            },
            
            escapeHtml: function(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            },
            
            debounce: function(func, wait, immediate) {
                let timeout;
                return function executedFunction(...args) {
                    const later = function() {
                        timeout = null;
                        if (!immediate) func(...args);
                    };
                    const callNow = immediate && !timeout;
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                    if (callNow) func(...args);
                };
            }
        },
        
        // API functions
        api: {
            request: async function(endpoint, options = {}) {
                const defaultOptions = {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                };
                
                const config = { ...defaultOptions, ...options };
                
                try {
                    const response = await fetch(ARMIS_MESSAGING.config.apiBaseUrl + endpoint, config);
                    
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    const data = await response.json();
                    return data;
                } catch (error) {
                    console.error('API request failed:', error);
                    ARMIS_MESSAGING.utils.showToast('Request failed: ' + error.message, 'danger');
                    throw error;
                }
            },
            
            sendMessage: function(threadId, content, messageType = 'TEXT', attachments = []) {
                return this.request('send_message.php', {
                    method: 'POST',
                    body: JSON.stringify({
                        thread_id: threadId,
                        content: content,
                        message_type: messageType,
                        attachments: attachments
                    })
                });
            },
            
            createThread: function(recipients, subject, content, threadType = 'PERSONAL') {
                return this.request('create_thread.php', {
                    method: 'POST',
                    body: JSON.stringify({
                        recipients: recipients,
                        subject: subject,
                        content: content,
                        thread_type: threadType
                    })
                });
            },
            
            getMessages: function(threadId, page = 1, limit = 50) {
                return this.request(`messages.php?thread_id=${threadId}&page=${page}&limit=${limit}`);
            },
            
            markAsRead: function(messageId) {
                return this.request('mark_read.php', {
                    method: 'POST',
                    body: JSON.stringify({ message_id: messageId })
                });
            },
            
            uploadFile: function(file, progressCallback) {
                return new Promise((resolve, reject) => {
                    const formData = new FormData();
                    formData.append('file', file);
                    
                    const xhr = new XMLHttpRequest();
                    
                    xhr.upload.addEventListener('progress', function(e) {
                        if (e.lengthComputable && progressCallback) {
                            const percentComplete = (e.loaded / e.total) * 100;
                            progressCallback(percentComplete);
                        }
                    });
                    
                    xhr.addEventListener('load', function() {
                        if (xhr.status === 200) {
                            try {
                                const response = JSON.parse(xhr.responseText);
                                resolve(response);
                            } catch (e) {
                                reject(new Error('Invalid response format'));
                            }
                        } else {
                            reject(new Error(`Upload failed with status: ${xhr.status}`));
                        }
                    });
                    
                    xhr.addEventListener('error', function() {
                        reject(new Error('Upload failed'));
                    });
                    
                    xhr.open('POST', ARMIS_MESSAGING.config.apiBaseUrl + 'upload_file.php');
                    xhr.send(formData);
                });
            }
        },
        
        // Message management
        messages: {
            init: function() {
                this.setupEventListeners();
                this.loadRecentMessages();
                this.startPolling();
            },
            
            setupEventListeners: function() {
                // Message composition
                const composeForm = document.getElementById('composeForm');
                if (composeForm) {
                    composeForm.addEventListener('submit', this.handleSendMessage.bind(this));
                }
                
                // File uploads
                const fileInput = document.getElementById('fileInput');
                if (fileInput) {
                    fileInput.addEventListener('change', this.handleFileSelect.bind(this));
                }
                
                // Drag and drop for files
                this.setupDragAndDrop();
                
                // Message list click handlers
                document.addEventListener('click', function(e) {
                    if (e.target.closest('.message-item')) {
                        const messageItem = e.target.closest('.message-item');
                        const threadId = messageItem.dataset.threadId;
                        if (threadId) {
                            ARMIS_MESSAGING.messages.openThread(threadId);
                        }
                    }
                });
            },
            
            handleSendMessage: async function(e) {
                e.preventDefault();
                
                const form = e.target;
                const formData = new FormData(form);
                
                const recipients = Array.from(form.querySelectorAll('input[name="recipients[]"]:checked'))
                    .map(input => input.value);
                
                if (recipients.length === 0) {
                    ARMIS_MESSAGING.utils.showToast('Please select at least one recipient', 'warning');
                    return;
                }
                
                const subject = formData.get('subject');
                const content = formData.get('content');
                
                if (!content.trim()) {
                    ARMIS_MESSAGING.utils.showToast('Please enter a message', 'warning');
                    return;
                }
                
                try {
                    // Show loading state
                    const submitBtn = form.querySelector('button[type="submit"]');
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
                    submitBtn.disabled = true;
                    
                    const result = await ARMIS_MESSAGING.api.createThread(recipients, subject, content);
                    
                    if (result.success) {
                        ARMIS_MESSAGING.utils.showToast('Message sent successfully', 'success');
                        form.reset();
                        
                        // Redirect to conversation
                        setTimeout(() => {
                            window.location.href = `thread.php?id=${result.thread_id}`;
                        }, 1000);
                    } else {
                        throw new Error(result.error || 'Failed to send message');
                    }
                } catch (error) {
                    console.error('Failed to send message:', error);
                    ARMIS_MESSAGING.utils.showToast('Failed to send message: ' + error.message, 'danger');
                } finally {
                    const submitBtn = form.querySelector('button[type="submit"]');
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            },
            
            handleFileSelect: function(e) {
                const files = Array.from(e.target.files);
                files.forEach(file => this.addAttachment(file));
            },
            
            addAttachment: function(file) {
                // Validate file size
                if (file.size > ARMIS_MESSAGING.config.maxFileSize) {
                    ARMIS_MESSAGING.utils.showToast(
                        `File "${file.name}" is too large. Maximum size is ${ARMIS_MESSAGING.utils.formatFileSize(ARMIS_MESSAGING.config.maxFileSize)}`,
                        'warning'
                    );
                    return;
                }
                
                // Validate file type
                const extension = file.name.split('.').pop().toLowerCase();
                if (!ARMIS_MESSAGING.config.allowedFileTypes.includes(extension)) {
                    ARMIS_MESSAGING.utils.showToast(
                        `File type "${extension}" is not allowed`,
                        'warning'
                    );
                    return;
                }
                
                const attachmentsList = document.getElementById('attachmentsList');
                if (!attachmentsList) return;
                
                const attachmentId = 'attachment_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                
                const attachmentItem = document.createElement('div');
                attachmentItem.className = 'attachment-item';
                attachmentItem.id = attachmentId;
                attachmentItem.innerHTML = `
                    <i class="fas fa-${ARMIS_MESSAGING.utils.getFileIcon(file.type)}"></i>
                    <div class="file-info">
                        <div class="file-name">${ARMIS_MESSAGING.utils.escapeHtml(file.name)}</div>
                        <div class="file-size">${ARMIS_MESSAGING.utils.formatFileSize(file.size)}</div>
                        <div class="progress mt-2" style="height: 4px;">
                            <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                        </div>
                    </div>
                    <button type="button" class="remove-attachment" onclick="removeAttachment('${attachmentId}')">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                
                attachmentsList.appendChild(attachmentItem);
                
                // Start upload
                this.uploadAttachment(file, attachmentId);
            },
            
            uploadAttachment: async function(file, attachmentId) {
                try {
                    const progressBar = document.querySelector(`#${attachmentId} .progress-bar`);
                    
                    const result = await ARMIS_MESSAGING.api.uploadFile(file, (progress) => {
                        if (progressBar) {
                            progressBar.style.width = progress + '%';
                        }
                    });
                    
                    if (result.success) {
                        // Store file info for sending with message
                        const attachmentItem = document.getElementById(attachmentId);
                        attachmentItem.dataset.fileId = result.file_id;
                        attachmentItem.dataset.filePath = result.file_path;
                        
                        progressBar.classList.add('bg-success');
                        
                        ARMIS_MESSAGING.utils.showToast(`"${file.name}" uploaded successfully`, 'success');
                    } else {
                        throw new Error(result.error || 'Upload failed');
                    }
                } catch (error) {
                    console.error('Upload failed:', error);
                    ARMIS_MESSAGING.utils.showToast('Upload failed: ' + error.message, 'danger');
                    
                    // Remove failed attachment
                    document.getElementById(attachmentId)?.remove();
                }
            },
            
            setupDragAndDrop: function() {
                const dropZones = document.querySelectorAll('.drop-zone');
                
                dropZones.forEach(zone => {
                    zone.addEventListener('dragover', function(e) {
                        e.preventDefault();
                        zone.classList.add('dragover');
                    });
                    
                    zone.addEventListener('dragleave', function(e) {
                        e.preventDefault();
                        zone.classList.remove('dragover');
                    });
                    
                    zone.addEventListener('drop', function(e) {
                        e.preventDefault();
                        zone.classList.remove('dragover');
                        
                        const files = Array.from(e.dataTransfer.files);
                        files.forEach(file => ARMIS_MESSAGING.messages.addAttachment(file));
                    });
                });
            },
            
            loadRecentMessages: async function() {
                try {
                    const data = await ARMIS_MESSAGING.api.request('recent_messages.php');
                    
                    if (data.success) {
                        this.renderMessageList(data.messages);
                        ARMIS_MESSAGING.state.unreadCount = data.unread_count;
                        this.updateUnreadBadge();
                    }
                } catch (error) {
                    console.error('Failed to load recent messages:', error);
                }
            },
            
            renderMessageList: function(messages) {
                const messagesList = document.getElementById('messagesList');
                if (!messagesList) return;
                
                if (messages.length === 0) {
                    messagesList.innerHTML = '<div class="text-center text-muted py-4">No messages found</div>';
                    return;
                }
                
                messagesList.innerHTML = messages.map(message => `
                    <div class="list-group-item list-group-item-action message-item ${message.is_read ? '' : 'unread'}" 
                         data-thread-id="${message.thread_id}">
                        <div class="d-flex w-100 justify-content-between">
                            <div class="d-flex align-items-start">
                                <div class="avatar me-3">
                                    <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center" 
                                         style="width: 40px; height: 40px;">
                                        <span class="text-white fw-bold">
                                            ${message.sender_name.charAt(0).toUpperCase()}
                                        </span>
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1">${ARMIS_MESSAGING.utils.escapeHtml(message.subject)}</h6>
                                    <p class="mb-1 text-muted">From: ${ARMIS_MESSAGING.utils.escapeHtml(message.sender_name)}</p>
                                    <small class="text-muted">${ARMIS_MESSAGING.utils.escapeHtml(message.content.substring(0, 100))}...</small>
                                </div>
                            </div>
                            <div class="text-end">
                                <small class="text-muted">${ARMIS_MESSAGING.utils.formatDateTime(message.sent_at)}</small>
                                ${!message.is_read ? '<br><span class="badge bg-primary">New</span>' : ''}
                            </div>
                        </div>
                    </div>
                `).join('');
            },
            
            openThread: function(threadId) {
                window.location.href = `thread.php?id=${threadId}`;
            },
            
            startPolling: function() {
                setInterval(() => {
                    this.loadRecentMessages();
                    ARMIS_MESSAGING.notifications.checkNew();
                }, ARMIS_MESSAGING.config.refreshInterval);
            },
            
            updateUnreadBadge: function() {
                const badges = document.querySelectorAll('.unread-badge');
                badges.forEach(badge => {
                    if (ARMIS_MESSAGING.state.unreadCount > 0) {
                        badge.textContent = ARMIS_MESSAGING.state.unreadCount;
                        badge.style.display = 'inline';
                    } else {
                        badge.style.display = 'none';
                    }
                });
            }
        },
        
        // Notification management
        notifications: {
            markAsRead: async function(notificationId) {
                try {
                    const result = await ARMIS_MESSAGING.api.request('mark_notification_read.php', {
                        method: 'POST',
                        body: JSON.stringify({ notification_id: notificationId })
                    });
                    
                    if (result.success) {
                        // Remove notification from UI
                        const notificationItem = document.querySelector(`[data-notification-id="${notificationId}"]`);
                        if (notificationItem) {
                            notificationItem.style.animation = 'fadeOut 0.3s ease-out';
                            setTimeout(() => {
                                notificationItem.remove();
                            }, 300);
                        }
                        
                        ARMIS_MESSAGING.utils.showToast('Notification marked as read', 'success');
                    }
                } catch (error) {
                    console.error('Failed to mark notification as read:', error);
                    ARMIS_MESSAGING.utils.showToast('Failed to mark notification as read', 'danger');
                }
            },
            
            checkNew: async function() {
                try {
                    const data = await ARMIS_MESSAGING.api.request('check_notifications.php');
                    
                    if (data.success && data.notifications.length > 0) {
                        this.showNewNotifications(data.notifications);
                    }
                } catch (error) {
                    console.error('Failed to check notifications:', error);
                }
            },
            
            showNewNotifications: function(notifications) {
                notifications.forEach(notification => {
                    if (notification.priority === 'URGENT') {
                        // Show as alert for urgent notifications
                        ARMIS_MESSAGING.utils.showToast(
                            `URGENT: ${notification.title}`,
                            'danger',
                            10000
                        );
                    } else {
                        // Show as regular toast
                        ARMIS_MESSAGING.utils.showToast(
                            notification.title,
                            'info'
                        );
                    }
                });
            }
        },
        
        // Announcement management
        announcements: {
            view: function(announcementId) {
                window.open(`announcement_details.php?id=${announcementId}`, '_blank');
            },
            
            markAsViewed: async function(announcementId) {
                try {
                    await ARMIS_MESSAGING.api.request('view_announcement.php', {
                        method: 'POST',
                        body: JSON.stringify({ announcement_id: announcementId })
                    });
                } catch (error) {
                    console.error('Failed to mark announcement as viewed:', error);
                }
            }
        },
        
        // Document management
        documents: {
            view: function(documentId) {
                window.open(`document_viewer.php?id=${documentId}`, '_blank');
            },
            
            download: function(documentId) {
                window.open(`download_document.php?id=${documentId}`, '_blank');
            }
        },
        
        // Main initialization
        init: function() {
            console.log('ARMIS Messaging module initialized');
            
            // Initialize components
            this.messages.init();
            
            // Setup global event listeners
            this.setupGlobalEventListeners();
            
            // Initialize WebSocket if available
            this.initWebSocket();
        },
        
        setupGlobalEventListeners: function() {
            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Ctrl/Cmd + Enter to send message
                if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                    const activeForm = document.querySelector('form.compose-form');
                    if (activeForm) {
                        activeForm.dispatchEvent(new Event('submit'));
                    }
                }
            });
            
            // Online/offline status
            window.addEventListener('online', function() {
                ARMIS_MESSAGING.state.isOnline = true;
                ARMIS_MESSAGING.utils.showToast('Connection restored', 'success');
            });
            
            window.addEventListener('offline', function() {
                ARMIS_MESSAGING.state.isOnline = false;
                ARMIS_MESSAGING.utils.showToast('Connection lost - working offline', 'warning');
            });
        },
        
        initWebSocket: function() {
            // WebSocket initialization for real-time features
            // This would be implemented if WebSocket server is available
            console.log('WebSocket support ready for implementation');
        }
    };
    
    // Global functions
    window.removeAttachment = function(attachmentId) {
        const attachment = document.getElementById(attachmentId);
        if (attachment) {
            attachment.style.animation = 'fadeOut 0.3s ease-out';
            setTimeout(() => {
                attachment.remove();
            }, 300);
        }
    };
    
    window.markNotificationRead = function(notificationId) {
        ARMIS_MESSAGING.notifications.markAsRead(notificationId);
    };
    
    window.viewDocument = function(documentId) {
        ARMIS_MESSAGING.documents.view(documentId);
    };
    
    window.downloadDocument = function(documentId) {
        ARMIS_MESSAGING.documents.download(documentId);
    };
    
    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        ARMIS_MESSAGING.init();
    });
    
    // Expose globally
    window.ARMIS_MESSAGING = ARMIS_MESSAGING;
    
})();