<?php

namespace DsElasticSearchBundle\Builder;

use DynamicSearchBundle\Logger\LoggerInterface;
use Elasticsearch\Client;

class ClientBuilder implements ClientBuilderInterface
{
    protected LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function build(array $indexOptions): Client
    {
        $client = \Elasticsearch\ClientBuilder::create();
        $client->setHosts($indexOptions['index']['hosts']);

        if (!empty($indexOptions['index']['credentials']['username']) && $indexOptions['index']['credentials']['password']) {
            $client->setBasicAuthentication($indexOptions['index']['credentials']['username'], $indexOptions['index']['credentials']['password']);
        }

        // @todo: add logger?
        //$psrLogger = $this->logger->getPsrLogger();

        return $client->build();
    }
}