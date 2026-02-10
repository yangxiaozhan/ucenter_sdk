<?php

declare(strict_types=1);

namespace UCenter\Sdk\Exception;

use Exception;

/**
 * UCenter API 异常
 */
class UCenterException extends Exception
{
    protected ?array $response = null;

    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null, ?array $response = null)
    {
        parent::__construct($message, $code, $previous);
        $this->response = $response;
    }

    public function getResponse(): ?array
    {
        return $this->response;
    }
}
