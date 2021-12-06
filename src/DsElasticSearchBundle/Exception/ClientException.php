<?php

namespace DsElasticSearchBundle\Exception;

final class ClientException extends \Exception
{
    public function __construct($message, ?\Exception $previousException = null)
    {
        parent::__construct(sprintf('Client Exception: %s', $message), 0, $previousException);
    }
}
