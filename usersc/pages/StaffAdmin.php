<?php
  require_once '../users/init.php';
  require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
  if (!securePage($_SERVER['PHP_SELF'])) { die(); }
  if (!$user->isLoggedIn() || $user->data()->role !== 'Admin') {
      Redirect::to($us_url_root . 'users/login.php');
      die();
  }
  class StaffAdmin {
      private $db;
      public function __construct($db) { $this->db = $db; }

      public function create($data) {
          $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
          $fields = [
              'svcNo','rankID','sName','fName','NRC','gender','unitID','category','svcStatus','appt','subRank','subWef','tempRank','tempWef','attestDate','intake','DOB','province','corps','bloodGp','trade','digitalID','prefix','marital','initials','titles','nok','nokTel','email','tel','unitAtt','username','password','role','renewDate','accStatus','createdBy'
          ];
          $insert = [];
          foreach($fields as $f) if(isset($data[$f])) $insert[$f] = $data[$f];
          $this->db->insert('Staff', $insert);
          return $this->db->lastId();
      }

      public function get($svcNo) {
          return $this->db->query("SELECT * FROM Staff WHERE svcNo = ?", [$svcNo])->first();
      }

      public function update($svcNo, $data) {
          if(isset($data['password']) && $data['password'] != '') {
              $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
          } else {
              unset($data['password']);
          }
          $this->db->update('Staff', $svcNo, $data, 'svcNo');
      }

      public function delete($svcNo) {
          $this->db->deleteById('Staff', $svcNo, 'svcNo');
      }

      public function list($limit = 50, $offset = 0) {
          return $this->db->query("SELECT * FROM Staff ORDER BY fName LIMIT ? OFFSET ?", [$limit, $offset])->results();
      }
  }
?>