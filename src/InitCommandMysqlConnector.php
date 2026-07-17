<?php

declare(strict_types=1);

namespace Luzrain\DbalDriver\AmphpMysql;

use Amp\Cancellation;
use Amp\Mysql\MysqlConfig;
use Amp\Mysql\MysqlConnection;
use Amp\Sql\SqlConfig;
use Amp\Sql\SqlConnector;

/**
 * Decorates a MySQL connector so every NEW pooled connection runs an init
 * command right after the handshake (e.g. SET SESSION MAX_EXECUTION_TIME).
 *
 * PDO exposes this via PDO::MYSQL_ATTR_INIT_COMMAND; amphp/mysql has no
 * equivalent hook, so the decoration happens at the connector seam — inside
 * pool->pop(), where suspension is legal.
 *
 * @implements SqlConnector<MysqlConfig, MysqlConnection>
 */
final class InitCommandMysqlConnector implements SqlConnector
{
    /**
     * @param SqlConnector<MysqlConfig, MysqlConnection> $delegate
     */
    public function __construct(
        private readonly SqlConnector $delegate,
        private readonly string $initCommand,
    ) {
    }

    public function connect(SqlConfig $config, ?Cancellation $cancellation = null): MysqlConnection
    {
        $connection = $this->delegate->connect($config, $cancellation);
        $connection->query($this->initCommand);

        return $connection;
    }
}
