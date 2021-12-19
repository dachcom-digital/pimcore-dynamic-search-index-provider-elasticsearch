<?php

namespace DsElasticSearchBundle\Builder;

use Elasticsearch\Client;

interface ClientBuilderInterface
{
    public function build(array $indexOptions): Client;
}