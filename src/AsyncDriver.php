<?php

declare(strict_types=1);

namespace Luzrain\DbalDriver\AmphpMysql;

use Amp\Dns\DnsRecord;
use Amp\Mysql\MysqlConfig;
use Amp\Mysql\MysqlConnection;
use Amp\Mysql\MysqlConnectionPool;
use Amp\Mysql\SocketMysqlConnector;
use Amp\Socket\ConnectContext;
use Amp\Socket\DnsSocketConnector;
use Amp\Socket\RetrySocketConnector;
use Amp\Sql\Common\SqlCommonConnectionPool;
use Doctrine\DBAL\Driver\AbstractMySQLDriver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Revolt\EventLoop;

final class AsyncDriver extends AbstractMySQLDriver
{
    private bool $init = false;

    /**
     * Extract connection from the pool
     * @var \Closure(): MysqlConnection
     */
    private \Closure $pop;

    /**
     * Return the extracted connection back to the pool
     * @var \Closure(MysqlConnection): void
     */
    private \Closure $push;

    /**
     * Key: Connection, Value: callback for release connection callback
     * @var \WeakMap<MysqlConnection, \Closure(): void>
     */
    private static \WeakMap $releaseCallback;

    public function connect(#[\SensitiveParameter] array $params): DriverConnection
    {
        if (!$this->init) {
            $this->init($params);
            $this->init = true;
        }

        $push = $this->push;
        $mysqlConnection = ($this->pop)();
        $releaseConnection = static function () use ($push, $mysqlConnection): void {
            $push($mysqlConnection);
        };
        self::$releaseCallback->offsetSet($mysqlConnection, $releaseConnection);

        return new Connection($mysqlConnection);
    }

    private function init(#[\SensitiveParameter] array $params): void
    {
        $connectTimeout = (float) ($params['driverOptions']['connect_timeout'] ?? 10);
        $connectRetries = (int) ($params['driverOptions']['connect_retries'] ?? 0);
        $dnsRecordType = $params['driverOptions']['dns_record_type'] ?? null;
        $maxConnections = (int) ($params['driverOptions']['max_connections'] ?? SqlCommonConnectionPool::DEFAULT_MAX_CONNECTIONS);
        $idleTimeout = (int) ($params['driverOptions']['idle_timeout'] ?? SqlCommonConnectionPool::DEFAULT_IDLE_TIMEOUT);

        $socketConnector = $connectRetries <= 0
            ? new DnsSocketConnector()
            : new RetrySocketConnector(
                delegate: new DnsSocketConnector(),
                maxAttempts: $connectRetries + 1,
                exponentialBackoffBase: 1,
            )
        ;

        $connectContext = (new ConnectContext())
            ->withConnectTimeout($connectTimeout)
            ->withDnsTypeRestriction(match ($dnsRecordType) {
                'A' => DnsRecord::A,
                'AAAA' => DnsRecord::AAAA,
                default => null,
            })
        ;

        /** @psalm-suppress ArgumentTypeCoercion */
        $pool = new MysqlConnectionPool(
            config: new MysqlConfig(
                host: $params['host'] ?? '',
                port: $params['port'] ?? MysqlConfig::DEFAULT_PORT,
                user: $params['user'] ?? null,
                password: $params['password'] ?? null,
                database: $params['dbname'] ?? null,
                context: $connectContext,
                charset: $params['charset'] ?? MysqlConfig::DEFAULT_CHARSET,
            ),
            maxConnections: $maxConnections,
            idleTimeout: $idleTimeout,
            connector: new SocketMysqlConnector($socketConnector),
        );

        $this->pop = (function (): MysqlConnection {
            /** @psalm-suppress UndefinedMethod */
            return $this->pop();
        })->bindTo($pool, $pool);

        $this->push = (function (MysqlConnection $connection): void {
            /** @psalm-suppress UndefinedMethod */
            $this->push($connection);
        })->bindTo($pool, $pool);

        /** @psalm-suppress PropertyTypeCoercion */
        self::$releaseCallback ??= new \WeakMap();
    }

    /**
     * @internal
     */
    public static function releaseConnection(MysqlConnection $mysqlConnection): void
    {
        /** @psalm-suppress PossiblyNullArgument */
        EventLoop::defer(self::$releaseCallback->offsetGet($mysqlConnection));
        self::$releaseCallback->offsetUnset($mysqlConnection);
    }
}
