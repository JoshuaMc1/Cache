<?php

namespace JoshuaMc1\Cache\Drivers;

use Exception;
use JoshuaMc1\Cache\Contracts\CacheInterface;
use PDO;

class DatabaseCacheDriver implements CacheInterface
{
    private $pdo;
    private $table;

    /**
     * Constructor.
     *
     * @param array $config
     *   An array of configuration options. The following options are supported:
     *
     *   - driver: The database driver to use. Supported drivers are "mysql"
     *       and "sqlite". Defaults to "mysql".
     *   - host: The hostname or IP of the database server. Only used with
     *       the "mysql" driver.
     *   - port: The port of the database server. Only used with the "mysql"
     *       driver.
     *   - username: The username to use when connecting to the database.
     *       Only used with the "mysql" driver.
     *   - password: The password to use when connecting to the database.
     *       Only used with the "mysql" driver.
     *   - database: The name of the database to use. Used with both the
     *       "mysql" and "sqlite" drivers.
     *   - charset: The character set to use. Defaults to "utf8mb4".
     *
     * @throws \Exception
     *   If there is an error connecting to the database.
     */
    public function __construct(array $config)
    {
        $this->table = 'cache';

        try {
            $driver = $config['driver'] ?? 'sqlite';

            $dsn = $this->buildDsn($config);

            $driver === 'sqlite' ?
                $this->pdo = new PDO($dsn) :
                $this->pdo = new PDO($dsn, $config['drivers'][$driver]['username'], $config['drivers'][$driver]['password']);

            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $this->ensureTableExists($driver);
        } catch (Exception $e) {
            throw new Exception("Error connecting to database: " . $e->getMessage());
        }
    }

    /**
     * Builds a DSN string from the given configuration options.
     *
     * @param array $config
     *   An array of configuration options. The following options are supported:
     *
     *   - driver: The database driver to use. Supported drivers are "mysql" and
     *       "sqlite". Defaults to "mysql".
     *   - host: The hostname or IP of the database server. Only used with the
     *       "mysql" driver.
     *   - port: The port of the database server. Only used with the "mysql"
     *       driver.
     *   - database: The name of the database to use. Used with both the
     *       "mysql" and "sqlite" drivers.
     *   - charset: The character set to use. Defaults to "utf8mb4".
     *
     * @return string
     *   The DSN string.
     */
    private function buildDsn(array $config): string
    {
        $driver = $config['driver'] ?? 'sqlite';

        if ($driver === 'sqlite') {
            $configDriver = $config['drivers'][$driver];

            $databasePath = $configDriver['database'];

            $directory = dirname($databasePath);

            if (!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }

            if (!file_exists($databasePath)) {
                touch($databasePath);
            }

            return sprintf('sqlite:%s', $databasePath);
        }

        return sprintf(
            '%s:host=%s;port=%d;dbname=%s;charset=%s',
            $driver,
            $config['drivers'][$driver]['host'],
            $config['drivers'][$driver]['port'],
            $config['drivers'][$driver]['database'],
            $config['drivers'][$driver]['charset']
        );
    }

    /**
     * Ensures that the cache table exists in the database.
     *
     * Depending on the given database driver, this method will either check
     * for the existence of a table in MySQL or check for the existence of a
     * table in SQLite.
     *
     * @param string $driver
     *   The database driver to use. Supported drivers are "mysql" and "sqlite".
     */
    private function ensureTableExists(string $driver)
    {
        $driver === 'sqlite' ?
            $this->ensureTableExistsSqlite() :
            $this->ensureTableExistsMysql();
    }

    /**
     * Ensures that the cache table exists in the MySQL database.
     *
     * Queries the information_schema.tables table to check if the cache table
     * exists. If the table does not exist, this method will create it using the
     * createTableMysql method.
     */
    private function ensureTableExistsMysql()
    {
        $sql = "
            SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
                AND table_name = :table
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':table' => $this->table]);
        $tableExists = (bool) $stmt->fetchColumn();

        if (!$tableExists) {
            $this->createTableMysql();
        }
    }

    /**
     * Creates the cache table in the MySQL database.
     *
     * The table contains the following columns:
     *
     * id: The unique identifier for the cache entry.
     * cache_key: The cache key.
     * cache_value: The cached value.
     * expires_at: The timestamp when the cache entry expires.
     * created_at: The timestamp when the cache entry was created.
     */
    private function createTableMysql()
    {
        $createTableSQL = "
            CREATE TABLE {$this->table} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cache_key VARCHAR(255) UNIQUE NOT NULL,
                cache_value TEXT NOT NULL,
                expires_at INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";

        $this->pdo->exec($createTableSQL);
    }

    /**
     * Checks if the cache table exists in the SQLite database and creates it if not.
     *
     * This method is similar to {@see ensureTableExistsMysql()} but uses a SQLite specific query.
     */
    private function ensureTableExistsSqlite()
    {
        $sql = "
            SELECT name
            FROM sqlite_master
            WHERE type = 'table'
                AND name = :table
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':table' => $this->table]);
        $tableExists = (bool) $stmt->fetchColumn();

        if (!$tableExists) {
            $this->createTableSqlite();
        }
    }

    /**
     * Creates the cache table in the SQLite database.
     *
     * The table contains the following columns:
     *
     * id: The unique identifier for the cache entry.
     * cache_key: The cache key.
     * cache_value: The cached value.
     * expires_at: The timestamp when the cache entry expires.
     * created_at: The timestamp when the cache entry was created.
     */
    private function createTableSqlite()
    {
        $createTableSQL = "
            CREATE TABLE {$this->table} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                cache_key TEXT UNIQUE NOT NULL,
                cache_value TEXT NOT NULL,
                expires_at INTEGER NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ";

        $this->pdo->exec($createTableSQL);
    }

    /**
     * Stores a cache entry in the database.
     *
     * @param string $key The key of the cache entry.
     * @param mixed $value The value to cache.
     * @param int $ttl The time-to-live for the cache entry in seconds. Defaults to 3600 seconds.
     *
     * @return void
     */
    public function set($key, $value, $ttl = 3600)
    {
        $expiresAt = time() + $ttl;
        $serializedValue = serialize($value);

        $sql = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ?
            "
                INSERT INTO {$this->table} (cache_key, cache_value, expires_at)
                VALUES (:cache_key, :cache_value, :expires_at)
                ON DUPLICATE KEY UPDATE
                    cache_value = VALUES(cache_value),
                    expires_at = VALUES(expires_at);
            " :
            "
                INSERT OR REPLACE INTO {$this->table} (cache_key, cache_value, expires_at)
                VALUES (:cache_key, :cache_value, :expires_at);
            ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':cache_key' => $key,
            ':cache_value' => $serializedValue,
            ':expires_at' => $expiresAt,
        ]);
    }

    /**
     * Retrieves a cache entry from the database.
     *
     * @param string $key The key of the cache entry to retrieve.
     *
     * @return mixed|null The value of the cache entry if it exists and hasn't expired, null otherwise.
     */
    public function get($key)
    {
        $sql = "SELECT cache_value, expires_at FROM {$this->table} WHERE cache_key = :cache_key";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':cache_key' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        if (time() > $row['expires_at']) {
            $this->delete($key);
            return null;
        }

        return unserialize($row['cache_value']);
    }

    /**
     * Checks if a cache entry exists in the database.
     *
     * @param string $key The key of the cache entry to check.
     *
     * @return bool True if the cache entry exists and hasn't expired, false otherwise.
     */
    public function has($key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Deletes a cache entry.
     *
     * @param string $key The key of the cache entry to delete.
     *
     * @return void
     */
    public function delete($key)
    {
        $sql = "DELETE FROM {$this->table} WHERE cache_key = :cache_key";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':cache_key' => $key]);
    }

    /**
     * Deletes all cache entries from the database.
     *
     * This method is usually used during development to clear out all the cache entries
     * when the application's codebase has changed.
     *
     * @return void
     */
    public function clear()
    {
        $sql = "DELETE FROM {$this->table}";
        $this->pdo->exec($sql);
    }
}
