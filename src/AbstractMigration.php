<?php
declare(strict_types=1);

namespace Nalogka\PdoMigrations;

use Nalogka\PdoMigrations\Exception\MigrationException;

abstract class AbstractMigration
{
    /**
     * @var \PDO
     */
    public $connection;

    public function __construct(\PDO $connection)
    {
        $this->connection = $connection;
    }

    public abstract function up() : void;

    /**
     * @param string $sql
     * @return bool|int
     * @throws MigrationException
     */
    public function execSql(string $sql)
    {
        $affected = $this->connection->exec($sql);
        if ($affected === false) {
            $err = $this->connection->errorInfo();
            if ($err[0] === '00000' || $err[0] === '01000') {
                return true;
            } else {
                throw new MigrationException("SQL ERROR {$err[0]}: {$err[2]}");
            }
        }

        return $affected;
    }
}
