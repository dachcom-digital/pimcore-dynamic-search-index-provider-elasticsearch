<?php

namespace DsElasticSearchBundle\Builder;

use Elasticsearch\Client;

interface ClientBuilderInterface
{
    /**
     * @param array $indexOptions
     *
     * @return Client
     */
    public function build(array $indexOptions);
}