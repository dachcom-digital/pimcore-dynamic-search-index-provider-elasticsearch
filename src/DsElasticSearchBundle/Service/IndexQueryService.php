<?php

namespace DsElasticSearchBundle\Service;

use Elasticsearch\Client;
use ONGR\ElasticsearchDSL\Search;

class IndexQueryService
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * @var array
     */
    protected $indexOptions;

    /**
     * @param Client $client
     * @param array  $indexOptions
     */
    public function __construct(Client $client, array $indexOptions)
    {
        $this->client = $client;
        $this->indexOptions = $indexOptions;
    }

    /**
     * @return Search
     */
    public function createSearch()
    {
        return new Search();
    }

    /**
     * @param array $query
     * @param array $params
     *
     * @return array|callable
     */
    public function search(array $query, array $params = [])
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

    /**
     * @return int
     */
    public function getIndexDocumentCount()
    {
        $body = [
            'index' => $this->getIndexName(),
            'body'  => [],
        ];

        $results = $this->client->count($body);

        return $results['count'];
    }

    /**
     * @return array
     */
    public function clearCache()
    {
        return $this->client->indices()->clearCache(['index' => $this->getIndexName()]);
    }

    /**
     * @return string
     */
    protected function getIndexName()
    {
        return $this->indexOptions['index']['identifier'];
    }

}
