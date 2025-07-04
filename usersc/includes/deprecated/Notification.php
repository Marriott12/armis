<?php
class Notification
{
    public function __construct() {
        return false;
    }

    private function getAllNotifications($all) {
        return false;
    }

    public function archiveOldNotifications($user_id) {
        return false;
    }

    public function addNotification($message, $user_id = -1) {
        return false;
    }

    public function setRead($notification_id, $read = true) {
        return false;
    }

    public function setReadAll($read = true) {
        return false;
    }

    public function getError() {
        return false;
    }

    public function getNotifications() {
          return false;
    }

    public function getCount() {
          return false;
    }

    public function getUnreadCount() {
          return false;
    }

    public function getLiveUnreadCount() {
	         return false;
    }

    public function getUnreadNotifications() {
          return false;
    }
}
