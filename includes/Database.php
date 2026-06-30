<?php
// ============================================================
// PersonaX v3 — includes/Database.php
// Singleton PDO wrapper with prepared statement helpers
// ============================================================

class Database {
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct() {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            DB_HOST, DB_PORT, DB_NAME
        );
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];
        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('[PersonaX DB] Connection failed: ' . $e->getMessage());
            http_response_code(503);
            die(json_encode(['error' => 'Database unavailable.']));
        }
    }

    public static function getInstance(): Database {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    /** Execute a query and return PDOStatement */
    public function query(string $sql, array $params = []): PDOStatement {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** Fetch all rows */
    public function fetchAll(string $sql, array $params = []): array {
        return $this->query($sql, $params)->fetchAll();
    }

    /** Fetch a single row */
    public function fetchOne(string $sql, array $params = []): ?array {
        $row = $this->query($sql, $params)->fetch();
        return $row ?: null;
    }

    /** Fetch a single scalar value */
    public function fetchValue(string $sql, array $params = []): mixed {
        $row = $this->query($sql, $params)->fetch(PDO::FETCH_NUM);
        return $row ? $row[0] : null;
    }

    /** Insert a row and return last insert ID */
    public function insert(string $table, array $data): int {
        $cols   = implode(', ', array_keys($data));
        $places = implode(', ', array_fill(0, count($data), '?'));
        $this->query("INSERT INTO `$table` ($cols) VALUES ($places)", array_values($data));
        return (int) $this->pdo->lastInsertId();
    }

    /** Update rows matching $where array */
    public function update(string $table, array $data, array $where): int {
        $set   = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($data)));
        $cond  = implode(' AND ', array_map(fn($k) => "`$k` = ?", array_keys($where)));
        $stmt  = $this->query(
            "UPDATE `$table` SET $set WHERE $cond",
            [...array_values($data), ...array_values($where)]
        );
        return $stmt->rowCount();
    }

    /** Begin / commit / rollback */
    public function begin():    void { $this->pdo->beginTransaction(); }
    public function commit():   void { $this->pdo->commit(); }
    public function rollback(): void { $this->pdo->rollBack(); }

    public function getPdo(): PDO { return $this->pdo; }
}
