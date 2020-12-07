<?php

namespace DsElasticSearchBundle\Exception;

final class ClientException extends \Exception
{
    /**
     * @param string          $message
     * @param \Exception|null $previousException
     */
    public function __construct($message, $previousException = null)
    {
        parent::__construct(sprintf('Client Exception: %s', $message), 0, $previousException);
    }
}
