<?php
/**
 * Database Class - PDO Wrapper + Singleton
 * Crystal Chess Tournament Booking Platform
 * core/Database.php
 */

class Database
{
    private static $instance = null;
    private $pdo;
    private $stmt;
    private $error;

    /**
     * Constructor - Initialize database connection
     */
    private function __construct()
    {
        try {
            // Database configuration constants (from config/config.php)
            $host = DB_HOST;
            $dbname = DB_NAME;
            $username = DB_USER;
            $password = DB_PASS;

            $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_PERSISTENT => true,
            ];

            $this->pdo = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }

    /**
     * Get singleton instance of Database
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get the PDO connection
     */
    public function getConnection()
    {
        return $this->pdo;
    }

    /**
     * Prepare SQL query
     */
    public function query($sql)
    {
        $this->stmt = $this->pdo->prepare($sql);
        return $this;
    }

    /**
     * Bind parameters
     */
    public function bind($param, $value, $type = null)
    {
        if (is_null($type)) {
            switch (true) {
                case is_int($value):
                    $type = PDO::PARAM_INT;
                    break;
                case is_bool($value):
                    $type = PDO::PARAM_BOOL;
                    break;
                case is_null($value):
                    $type = PDO::PARAM_NULL;
                    break;
                default:
                    $type = PDO::PARAM_STR;
            }
        }

        $this->stmt->bindValue($param, $value, $type);
        return $this;
    }

    /**
     * Execute prepared statement
     */
    public function execute($params = [])
    {
        try {
            if (empty($params)) {
                return $this->stmt->execute();
            }
            return $this->stmt->execute($params);
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            error_log("Database Error: " . $this->error);
            return false;
        }
    }

    /**
     * Fetch all results as array
     */
    public function fetchAll()
    {
        $this->execute();
        return $this->stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fetch single result
     */
    public function fetch()
    {
        $this->execute();
        return $this->stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Fetch single column value
     */
    public function fetchColumn()
    {
        $this->execute();
        return $this->stmt->fetchColumn();
    }

    /**
     * Get row count
     */
    public function rowCount()
    {
        return $this->stmt->rowCount();
    }

    /**
     * Get last insert ID
     */
    public function lastInsertId()
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * Begin transaction
     */
    public function beginTransaction()
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit()
    {
        return $this->pdo->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback()
    {
        return $this->pdo->rollBack();
    }

    /**
     * Get error message
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * Simple SELECT query helper
     */
    public function select($table, $columns = '*', $where = [], $orderBy = '', $limit = '')
    {
        $sql = "SELECT $columns FROM $table";

        if (!empty($where)) {
            $conditions = [];
            foreach ($where as $key => $value) {
                $conditions[] = "$key = :$key";
            }
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        if ($orderBy) {
            $sql .= " ORDER BY $orderBy";
        }

        if ($limit) {
            $sql .= " LIMIT $limit";
        }

        $this->query($sql);

        if (!empty($where)) {
            foreach ($where as $key => $value) {
                $this->bind(":$key", $value);
            }
        }

        return $this->fetchAll();
    }

    /**
     * Simple INSERT query helper
     */
    public function insert($table, $data)
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
        $this->query($sql);

        foreach ($data as $key => $value) {
            $this->bind(":$key", $value);
        }

        if ($this->execute()) {
            return $this->lastInsertId();
        }

        return false;
    }

    /**
     * Simple UPDATE query helper
     */
    public function update($table, $data, $where)
    {
        $set = [];
        foreach ($data as $key => $value) {
            $set[] = "$key = :$key";
        }

        $conditions = [];
        foreach ($where as $key => $value) {
            $conditions[] = "$key = :where_$key";
        }

        $sql = "UPDATE $table SET " . implode(', ', $set) . " WHERE " . implode(' AND ', $conditions);
        $this->query($sql);

        foreach ($data as $key => $value) {
            $this->bind(":$key", $value);
        }

        foreach ($where as $key => $value) {
            $this->bind(":where_$key", $value);
        }

        return $this->execute();
    }

    /**
     * Simple DELETE query helper
     */
    public function delete($table, $where)
    {
        $conditions = [];
        foreach ($where as $key => $value) {
            $conditions[] = "$key = :$key";
        }

        $sql = "DELETE FROM $table WHERE " . implode(' AND ', $conditions);
        $this->query($sql);

        foreach ($where as $key => $value) {
            $this->bind(":$key", $value);
        }

        return $this->execute();
    }

    /**
     * Check if record exists
     */
    public function exists($table, $where)
    {
        $conditions = [];
        foreach ($where as $key => $value) {
            $conditions[] = "$key = :$key";
        }

        $sql = "SELECT COUNT(*) FROM $table WHERE " . implode(' AND ', $conditions);
        $this->query($sql);

        foreach ($where as $key => $value) {
            $this->bind(":$key", $value);
        }

        return $this->fetchColumn() > 0;
    }

    /**
     * Count records
     */
    public function count($table, $where = [])
    {
        $sql = "SELECT COUNT(*) FROM $table";

        if (!empty($where)) {
            $conditions = [];
            foreach ($where as $key => $value) {
                $conditions[] = "$key = :$key";
            }
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $this->query($sql);

        if (!empty($where)) {
            foreach ($where as $key => $value) {
                $this->bind(":$key", $value);
            }
        }

        return $this->fetchColumn();
    }
}
