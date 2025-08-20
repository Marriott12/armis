<?php
/**
 * ARMIS Messaging Service
 * Core service class for messaging and communication management
 */

class MessagingService {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Get messaging summary statistics for a user
     */
    public function getMessagingSummary($userId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(CASE WHEN mp.is_active = 1 AND m.is_read = 0 THEN 1 END) as unread_messages,
                    COUNT(CASE WHEN mp.is_active = 1 THEN 1 END) as total_messages,
                    COUNT(CASE WHEN mt.created_by = ? THEN 1 END) as sent_messages,
                    COUNT(CASE WHEN n.is_read = 0 THEN 1 END) as unread_notifications
                FROM message_participants mp
                LEFT JOIN messages m ON mp.thread_id = m.thread_id
                LEFT JOIN message_threads mt ON mp.thread_id = mt.id
                LEFT JOIN notifications n ON n.recipient_id = ?
                WHERE mp.user_id = ?
            ");
            
            $stmt->execute([$userId, $userId, $userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
                'unread_messages' => 0,
                'total_messages' => 0,
                'sent_messages' => 0,
                'unread_notifications' => 0
            ];
        } catch (Exception $e) {
            error_log("Error getting messaging summary: " . $e->getMessage());
            return [
                'unread_messages' => 0,
                'total_messages' => 0,
                'sent_messages' => 0,
                'unread_notifications' => 0
            ];
        }
    }
    
    /**
     * Get recent messages for a user
     */
    public function getRecentMessages($userId, $limit = 10) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    mt.id as thread_id,
                    mt.subject,
                    mt.thread_type,
                    mt.priority,
                    m.id as message_id,
                    m.content,
                    m.sent_at,
                    m.is_read,
                    CONCAT(u.fname, ' ', u.lname) as sender_name,
                    u.id as sender_id
                FROM message_threads mt
                JOIN message_participants mp ON mt.id = mp.thread_id
                JOIN messages m ON mt.id = m.thread_id
                JOIN users u ON m.sender_id = u.id
                WHERE mp.user_id = ? AND mp.is_active = 1
                AND m.id = (
                    SELECT MAX(id) FROM messages 
                    WHERE thread_id = mt.id
                )
                ORDER BY m.sent_at DESC
                LIMIT ?
            ");
            
            $stmt->execute([$userId, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting recent messages: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get unread notifications for a user
     */
    public function getUnreadNotifications($userId, $limit = 10) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    n.id,
                    n.title,
                    n.message,
                    n.priority,
                    n.created_at,
                    nt.name as type_name,
                    nt.icon,
                    nt.color
                FROM notifications n
                JOIN notification_types nt ON n.type_id = nt.id
                WHERE n.recipient_id = ? 
                AND n.is_read = 0
                AND (n.expires_at IS NULL OR n.expires_at > NOW())
                ORDER BY n.priority DESC, n.created_at DESC
                LIMIT ?
            ");
            
            $stmt->execute([$userId, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting unread notifications: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get active announcements
     */
    public function getActiveAnnouncements($limit = 5) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    a.id,
                    a.title,
                    a.content,
                    a.category,
                    a.priority,
                    a.target_audience,
                    a.target_value,
                    a.published_at,
                    a.expires_at,
                    a.view_count,
                    CONCAT(u.fname, ' ', u.lname) as creator_name
                FROM announcements a
                JOIN users u ON a.created_by = u.id
                WHERE a.is_active = 1
                AND a.published_at <= NOW()
                AND (a.expires_at IS NULL OR a.expires_at > NOW())
                ORDER BY a.priority DESC, a.published_at DESC
                LIMIT ?
            ");
            
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting active announcements: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get recent shared documents
     */
    public function getRecentSharedDocuments($limit = 5) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    sd.id,
                    sd.name,
                    sd.description,
                    sd.file_size,
                    sd.mime_type,
                    sd.category,
                    sd.access_level,
                    sd.download_count,
                    sd.created_at,
                    CONCAT(u.fname, ' ', u.lname) as uploader_name
                FROM shared_documents sd
                JOIN users u ON sd.uploaded_by = u.id
                WHERE sd.is_active = 1
                AND (
                    sd.access_level = 'PUBLIC'
                    OR sd.uploaded_by = ?
                    OR EXISTS (
                        SELECT 1 FROM document_permissions dp
                        WHERE dp.document_id = sd.id
                        AND ((dp.permission_type = 'USER' AND dp.permission_value = ?)
                             OR (dp.permission_type = 'ROLE' AND dp.permission_value = ?)
                             OR (dp.permission_type = 'UNIT' AND dp.permission_value = ?))
                    )
                )
                ORDER BY sd.created_at DESC
                LIMIT ?
            ");
            
            $currentUserId = $_SESSION['user_id'] ?? 0;
            $currentRole = $_SESSION['role'] ?? 'user';
            $currentUnit = $_SESSION['unit_id'] ?? 0;
            
            $stmt->execute([$currentUserId, $currentUserId, $currentRole, $currentUnit, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting recent shared documents: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Send a message
     */
    public function sendMessage($recipients, $subject, $content, $threadType = 'PERSONAL', $priority = 'NORMAL') {
        try {
            $this->pdo->beginTransaction();
            
            // Create message thread
            $stmt = $this->pdo->prepare("
                INSERT INTO message_threads (
                    subject, thread_type, priority, created_by
                ) VALUES (?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $subject,
                $threadType,
                $priority,
                $_SESSION['user_id']
            ]);
            
            if (!$result) {
                throw new Exception('Failed to create message thread');
            }
            
            $threadId = $this->pdo->lastInsertId();
            
            // Add sender as participant
            $participantStmt = $this->pdo->prepare("
                INSERT INTO message_participants (thread_id, user_id, role)
                VALUES (?, ?, 'SENDER')
            ");
            $participantStmt->execute([$threadId, $_SESSION['user_id']]);
            
            // Add recipients as participants
            foreach ($recipients as $recipientId) {
                $participantStmt->execute([$threadId, $recipientId, 'RECIPIENT']);
            }
            
            // Create the message
            $messageStmt = $this->pdo->prepare("
                INSERT INTO messages (
                    thread_id, sender_id, content, message_type
                ) VALUES (?, ?, ?, 'TEXT')
            ");
            
            $messageStmt->execute([$threadId, $_SESSION['user_id'], $content]);
            $messageId = $this->pdo->lastInsertId();
            
            // Create notifications for recipients
            $this->createMessageNotifications($recipients, $subject, $_SESSION['user_id']);
            
            $this->pdo->commit();
            logMessagingActivity('message_sent', 'Message sent', 'message', $messageId);
            
            return $threadId;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Error sending message: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create announcement
     */
    public function createAnnouncement($data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO announcements (
                    title, content, category, priority, target_audience,
                    target_value, is_active, published_at, expires_at, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $data['title'],
                $data['content'],
                $data['category'] ?? null,
                $data['priority'] ?? 'NORMAL',
                $data['target_audience'] ?? 'ALL',
                $data['target_value'] ?? null,
                $data['is_active'] ?? 1,
                $data['published_at'] ?? date('Y-m-d H:i:s'),
                $data['expires_at'] ?? null,
                $_SESSION['user_id']
            ]);
            
            if ($result) {
                $announcementId = $this->pdo->lastInsertId();
                
                // Create notifications for target audience
                $this->createAnnouncementNotifications($announcementId, $data);
                
                logMessagingActivity('announcement_created', 'Announcement created', 'announcement', $announcementId);
                return $announcementId;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Error creating announcement: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Upload shared document
     */
    public function uploadSharedDocument($fileData, $metadata) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO shared_documents (
                    name, description, file_path, file_size, mime_type,
                    category, access_level, uploaded_by, version
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $metadata['name'],
                $metadata['description'] ?? null,
                $fileData['file_path'],
                $fileData['file_size'],
                $fileData['mime_type'],
                $metadata['category'] ?? null,
                $metadata['access_level'] ?? 'PRIVATE',
                $_SESSION['user_id'],
                $metadata['version'] ?? 1
            ]);
            
            if ($result) {
                $documentId = $this->pdo->lastInsertId();
                
                // Add document permissions if specified
                if (!empty($metadata['permissions'])) {
                    $this->addDocumentPermissions($documentId, $metadata['permissions']);
                }
                
                logMessagingActivity('document_uploaded', 'Document shared', 'document', $documentId);
                return $documentId;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Error uploading shared document: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Mark message as read
     */
    public function markMessageAsRead($messageId, $userId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE messages 
                SET is_read = 1 
                WHERE id = ? 
                AND thread_id IN (
                    SELECT thread_id FROM message_participants 
                    WHERE user_id = ?
                )
            ");
            
            return $stmt->execute([$messageId, $userId]);
        } catch (Exception $e) {
            error_log("Error marking message as read: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Mark notification as read
     */
    public function markNotificationAsRead($notificationId, $userId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE notifications 
                SET is_read = 1, read_at = NOW()
                WHERE id = ? AND recipient_id = ?
            ");
            
            return $stmt->execute([$notificationId, $userId]);
        } catch (Exception $e) {
            error_log("Error marking notification as read: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create message notifications
     */
    private function createMessageNotifications($recipients, $subject, $senderId) {
        try {
            // Get sender name
            $stmt = $this->pdo->prepare("
                SELECT CONCAT(fname, ' ', lname) as name FROM users WHERE id = ?
            ");
            $stmt->execute([$senderId]);
            $senderName = $stmt->fetchColumn();
            
            // Get message notification type
            $stmt = $this->pdo->prepare("
                SELECT id FROM notification_types WHERE name = 'message_received'
            ");
            $stmt->execute();
            $typeId = $stmt->fetchColumn();
            
            if ($typeId) {
                $notificationStmt = $this->pdo->prepare("
                    INSERT INTO notifications (
                        type_id, recipient_id, title, message, priority
                    ) VALUES (?, ?, ?, ?, 'NORMAL')
                ");
                
                foreach ($recipients as $recipientId) {
                    $notificationStmt->execute([
                        $typeId,
                        $recipientId,
                        'New Message',
                        "You received a new message from {$senderName}: {$subject}"
                    ]);
                }
            }
        } catch (Exception $e) {
            error_log("Error creating message notifications: " . $e->getMessage());
        }
    }
    
    /**
     * Create announcement notifications
     */
    private function createAnnouncementNotifications($announcementId, $data) {
        try {
            // Get notification type
            $stmt = $this->pdo->prepare("
                SELECT id FROM notification_types WHERE name = 'system_alert'
            ");
            $stmt->execute();
            $typeId = $stmt->fetchColumn();
            
            if (!$typeId) return;
            
            // Determine recipients based on target audience
            $recipients = [];
            
            switch ($data['target_audience']) {
                case 'ALL':
                    $stmt = $this->pdo->query("SELECT id FROM users WHERE status = 'ACTIVE'");
                    $recipients = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    break;
                    
                case 'ROLE':
                    $stmt = $this->pdo->prepare("SELECT id FROM users WHERE role = ? AND status = 'ACTIVE'");
                    $stmt->execute([$data['target_value']]);
                    $recipients = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    break;
                    
                case 'UNIT':
                    $stmt = $this->pdo->prepare("SELECT id FROM users WHERE unit_id = ? AND status = 'ACTIVE'");
                    $stmt->execute([$data['target_value']]);
                    $recipients = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    break;
            }
            
            // Create notifications
            if (!empty($recipients)) {
                $notificationStmt = $this->pdo->prepare("
                    INSERT INTO notifications (
                        type_id, recipient_id, title, message, priority
                    ) VALUES (?, ?, ?, ?, ?)
                ");
                
                foreach ($recipients as $recipientId) {
                    $notificationStmt->execute([
                        $typeId,
                        $recipientId,
                        'New Announcement: ' . $data['title'],
                        substr($data['content'], 0, 200) . '...',
                        $data['priority'] ?? 'NORMAL'
                    ]);
                }
            }
        } catch (Exception $e) {
            error_log("Error creating announcement notifications: " . $e->getMessage());
        }
    }
    
    /**
     * Add document permissions
     */
    private function addDocumentPermissions($documentId, $permissions) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO document_permissions (
                    document_id, permission_type, permission_value, 
                    access_type, granted_by
                ) VALUES (?, ?, ?, ?, ?)
            ");
            
            foreach ($permissions as $permission) {
                $stmt->execute([
                    $documentId,
                    $permission['type'], // USER, ROLE, UNIT
                    $permission['value'],
                    $permission['access'] ?? 'read',
                    $_SESSION['user_id']
                ]);
            }
        } catch (Exception $e) {
            error_log("Error adding document permissions: " . $e->getMessage());
        }
    }
}
?>