<?php

declare(strict_types=1);

namespace Luzrain\DbalDriver\AmphpMysql;

use Amp\Sql\SqlQueryError;
use Doctrine\DBAL\Driver\AbstractException;

final class Exception extends AbstractException
{
    public function __construct(\Throwable $previous)
    {
        $message = $previous->getMessage();
        $code = (int) $previous->getCode();
        $sqlState = null;

        if ($previous instanceof SqlQueryError && \preg_match('/^MySQL error \((\d+)\): #([A-Z0-9]{5})\b/', $message, $matches) === 1) {
            $code = (int) $matches[1];
            $sqlState = $matches[2];
        }

        parent::__construct($message, $sqlState, $code, $previous);
    }
}
