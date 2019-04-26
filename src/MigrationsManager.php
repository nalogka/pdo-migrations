<?php
declare(strict_types=1);

namespace Nalogka\PdoMigrations;

use Nalogka\PdoMigrations\Exception\ConfigurationException;
use Nalogka\PdoMigrations\Exception\MigrationException;

class MigrationsManager
{
    /**
     * @var \PDO Database connection
     */
    private $connection;

    /**
     * @var Configuration
     */
    private $configuration;

    /**
     * MigrationsManager constructor.
     * @param Configuration $configuration
     */
    public function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;
    }

    public function getMigrationsList()
    {
        $files = scandir($this->configuration->migrationsPath);

        $migrations = [];

        foreach ($files as $file) {
            if (!preg_match('/(^Version)(\d{14})(.php)/', $file, $matches)) {
                continue;
            }

            $migrationDate = \DateTimeImmutable::createFromFormat('YmdHis', $matches[2]);

            $migrations[] = [
                'version' => $matches[2],
                'timestamp' => $migrationDate->getTimestamp()
            ];
        }

        usort($migrations, function ($a, $b) {
            return $a['timestamp'] <=> $b['timestamp'];
        });

        return array_column($migrations, 'version');
    }

    /**
     * @return array
     * @throws ConfigurationException
     */
    public function getExecutedMigrationsList()
    {
        $this->initialize();

        $sql = "SELECT * FROM `{$this->configuration->tableName}` ORDER BY executed_at ASC";

        $stmt = $this->connection->query($sql);

        $executedMigrations = [];

        foreach ($stmt->fetchAll() as $row) {
            $executedMigrations[] = $row['version'];
        }

        return $executedMigrations;
    }

    /**
     * @return array
     * @throws ConfigurationException
     */
    public function getMigrationsToExecuteList()
    {
        return array_diff($this->getMigrationsList(), $this->getExecutedMigrationsList());
    }

    /**
     * @param array $migrations
     * @throws ConfigurationException
     * @throws MigrationException
     */
    public function executeMigrations(array $migrations)
    {
        foreach ($migrations as $version) {
            $this->executeMigration($version);
        }
    }

    /**
     * @param string $version
     * @throws ConfigurationException
     * @throws MigrationException
     */
    public function executeMigration(string $version): void
    {
        $this->initialize();

        $className = $this->getClassReferenceByVersion($version);

        $filePath = $this->getFilePathByVersion($version);

        if (!file_exists($filePath)) {
            throw new MigrationException("Not found migration file: {$filePath}");
        }

        require_once($filePath);

        if (!class_exists($className)) {
            throw new MigrationException("Not found migration class {$className} in {$filePath}");
        }

        /**
         * @var AbstractMigration $migrationObject
         */
        $migrationObject = new $className($this->connection);

        if (!$migrationObject instanceof AbstractMigration) {
            throw new MigrationException("Migrations {$className} not instance of " . AbstractMigration::class);
        }

        $migrationObject->up();

        $sql = "INSERT INTO `{$this->configuration->tableName}` (`version`, `executed_at`) VALUES ('{$version}', NOW())";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute();
    }

    private function getFilePathByVersion(string $version)
    {
        $fileName = "Version{$version}.php";
        $migrationsDir = rtrim($this->configuration->migrationsPath, "/");
        return "{$migrationsDir}/{$fileName}";
    }

    private function getClassReferenceByVersion(string $version)
    {
        return "{$this->configuration->migrationsNamespace}\\Version{$version}";
    }

    /**
     * Initialize migrations table
     * @throws ConfigurationException
     */
    private function initialize(): void
    {
        if ($this->connection === null) {
            $this->createConnection();
        }

        $sql = "CREATE TABLE IF NOT EXISTS `{$this->configuration->tableName}` (
            `version` varchar(14) COLLATE utf8mb4_unicode_ci NOT NULL,
            `executed_at` datetime NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET={$this->configuration->tableCharset} COLLATE={$this->configuration->tableCollate};";

        $this->connection->exec($sql);
    }

    /**
     * @throws ConfigurationException
     */
    private function createConnection(): void
    {
        $parsedUrl = parse_url($this->configuration->databaseUrl);

        $dsn = "{$parsedUrl['scheme']}:";

        if (empty($parsedUrl['host'])) {
            throw new ConfigurationException("Не передан адрес сервера базы данных");
        }

        $dsn .= "host={$parsedUrl['host']};";

        if (!empty($parsedUrl['port'])) {
            $dsn .= "port={$parsedUrl['port']};";
        }

        if (empty($parsedUrl['path'])) {
            throw new ConfigurationException("Не передано имя базы данных");
        }

        $databaseName = trim($parsedUrl['path'], "/");

        $dsn .= "dbname={$databaseName};";

        $dsn .= "charset={$this->configuration->databaseCharset};";

        $username = null;
        if (!empty($parsedUrl['user'])) {
            $username = $parsedUrl['user'];
        }

        $password = null;
        if (!empty($parsedUrl['pass'])) {
            $password = $parsedUrl['pass'];
        }

        $this->connection = new \PDO($dsn, $username, $password);
    }
}
