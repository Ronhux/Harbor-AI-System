<?php

/**
 * Database Connection Configuration
 * Reusable PDO connection for the Harbor AI system
 */

if (!class_exists('HarborDb', false)) {
    class HarborDb {
        private static $instance = null;
        private $pdo;

        private function __construct() {
            try {
                $host = 'localhost';
                $db = 'harbor_ai';
                $user = 'root';
                $pass = '';
                $charset = 'utf8mb4';

                $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ];

                $this->pdo = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                throw new PDOException($e->getMessage(), (int)$e->getCode());
            }
        }

        public static function getInstance() {
            if (self::$instance == null) {
                self::$instance = new HarborDb();
            }
            return self::$instance;
        }

        public function getConnection() {
            return $this->pdo;
        }

        private function __clone() {}

        public function __wakeup() {
            throw new Exception("Cannot unserialize singleton");
        }
    }
}

if (!class_exists('Database', false)) {
    class_alias(HarborDb::class, 'Database');
}

?>