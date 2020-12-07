<?php

namespace DsElasticSearchBundle\Builder;

use DynamicSearchBundle\Logger\LoggerInterface;

class ClientBuilder implements ClientBuilderInterface
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * {@inheritDoc}
     */
    public function build(array $indexOptions)
    {
        $client = \Elasticsearch\ClientBuilder::create();
        $client->setHosts($indexOptions['index']['hosts']);

        // @todo: add logger?
        //$psrLogger = $this->logger->getPsrLogger();

        return $client->build();
    }
}