<?php

namespace DsElasticSearchBundle\Service;

use Elasticsearch\Client;
use ONGR\ElasticsearchDSL\Search;

class IndexQueryService
{
    protected Client $client;
    protected array $indexOptions;

    public function __construct(Client $client, array $indexOptions)
    {
        $this->client = $client;
        $this->indexOptions = $indexOptions;
    }

    public function createSearch(): Search
    {
        return new Search();
    }

    public function search(array $query, array $params = []): array
    {
        $requestParams = [
            'index' => $this->getIndexName(),
            'body'  => $query,
        ];

        if (!empty($params)) {
            $requestParams = array_merge($requestParams, $params);
        }

        return $this->client->search($requestParams);
    }

    public function getIndexDocumentCount(): int
    {
        $body = [
            'index' => $this->getIndexName(),
            'body'  => [],
        ];

        $results = $this->client->count($body);

        return $results['count'];
    }

    public function clearCache(): array
    {
        return $this->client->indices()->clearCache(['index' => $this->getIndexName()]);
    }

    protected function getIndexName(): string
    {
        return $this->indexOptions['index']['identifier'];
    }

}
