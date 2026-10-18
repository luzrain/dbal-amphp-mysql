## Async Doctrine DBAL driver for AMPHP Mysql client
![PHP >=8.2](https://img.shields.io/badge/PHP->=8.2-777bb3.svg)

[[DBAL driver for AMPHP Postgres]](https://github.com/luzrain/dbal-amphp-postgres) [[DBAL driver for AMPHP Mysql]](#)

## Installation
``` bash
$ composer require luzrain/dbal-amphp-mysql
```

#### Example of usage
```php
use Doctrine\DBAL\DriverManager;
use Luzrain\DbalDriver\AmphpMysql\AsyncConection;
use Luzrain\DbalDriver\AmphpMysql\AsyncDriver;

$connectionParams = [
    'dbname' => 'mydb',
    'user' => 'user',
    'password' => 'secret',
    'host' => 'localhost',
    'driverClass' => AsyncDriver::class,
    'wrapperClass' => AsyncConection::class,
    'driverOptions' => [
        'max_connections' => 15,
        'idle_timeout' => 60,
        'connect_timeout' => 10,
        'connect_retries' => 1,
        'dns_record_type' => 'A',
    ],
];
$conn = DriverManager::getConnection($connectionParams);
```
