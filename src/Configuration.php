<?php
declare(strict_types = 1);

namespace Nalogka\PdoMigrations;


use PDO;

class Configuration
{
    /**
     * @var string
     */
    public $databaseUrl;

    /**
     * @var string
     */
    public $databaseCharset = "utf8mb4";

    /**
     * @var string
     */
    public $tableName;

    /**
     * @var string
     */
    public $tableCharset = "utf8mb4";

    /**
     * @var string
     */
    public $tableCollate = "utf8mb4_unicode_ci";

    /**
     * @var string
     */
    public $migrationsPath;

    /**
     * @var string
     */
    public $migrationsNamespace = "PdoMigrations";

    /**
     * @var array A key=>value array of driver-specific connection options.
     */
    public $pdoOptions = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ];
}