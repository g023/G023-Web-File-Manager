<?php
class StateManager {
  private $db;
  
  public function __construct($db) {
    $this->db = $db;
  }
  
  public function saveState($key, $value) {
    $stmt = $this->db->prepare("INSERT OR REPLACE INTO ui_state (state_key, state_value, updated_at) VALUES (?, ?, datetime('now'))");
    $stmt->bindValue(1, $key, SQLITE3_TEXT);
    $stmt->bindValue(2, json_encode($value), SQLITE3_TEXT);
    return $stmt->execute();
  }
  
  public function getState($key) {
    $stmt = $this->db->prepare("SELECT state_value FROM ui_state WHERE state_key = ?");
    $stmt->bindValue(1, $key, SQLITE3_TEXT);
    $result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    return $result ? json_decode($result['state_value'], true) : null;
  }
  
  public function getAllState() {
    $states = [];
    $result = $this->db->query("SELECT state_key, state_value FROM ui_state");
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
      $states[$row['state_key']] = json_decode($row['state_value'], true);
    }
    return $states;
  }
  
  public function clearState() {
    return $this->db->exec("DELETE FROM ui_state");
  }
}
