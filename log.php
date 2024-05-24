<?php

class log
{
    private SQLite3 $db;
    function __construct()
    {
        try {
            $this->db = new SQLite3(ROOT . '/log.db');
            $this->db->exec('CREATE TABLE IF NOT EXISTS log (id INTEGER PRIMARY KEY AUTOINCREMENT, file TEXT, md5 TEXT)');
            $this->db->exec('CREATE UNIQUE INDEX IF NOT EXISTS file_index ON log (file)');
        }
        catch (Exception $e) {
            exit_log($e->getMessage() . PHP_EOL);
        }
    }
    public function set($file, $md5)
    {
        $stmt = $this->db->prepare('INSERT INTO log (file, md5) VALUES (:file, :md5)');
        $stmt->bindValue(':file', $file, SQLITE3_TEXT);
        $stmt->bindValue(':md5', $md5, SQLITE3_TEXT);

        $stmt->execute();
    }
    public function check($file)
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM log WHERE file = :file');
        $stmt->bindValue(':file', $file, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_NUM);
        return $row[0] > 0;
    }
}