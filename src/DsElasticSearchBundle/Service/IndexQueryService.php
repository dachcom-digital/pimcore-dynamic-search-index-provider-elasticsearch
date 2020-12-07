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
    protected $indexOptions = [];

    /**
     * @param Client $client
     * @param array  $indexOptions
     */
    public function __construct(Client $client, array $indexOptions)
    {
        $this->client = $client;
        $this->indexOptions = $indexOptions;
    }

    public function createSearch()
    {
        return new Search();
    }

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

    public function getScrollConfiguration($raw, $scrollDuration)
    {
        $scrollConfig = [];
        if (isset($raw['_scroll_id'])) {
            $scrollConfig['_scroll_id'] = $raw['_scroll_id'];
            $scrollConfig['duration'] = $scrollDuration;
        }

        return $scrollConfig;
    }

    public function getIndexDocumentCount()
    {
        $body = [
            'index' => $this->getIndexName(),
            'body'  => [],
        ];

        $results = $this->client->count($body);

        return $results['count'];
    }

    public function scroll($scrollId, $scrollDuration = '5m')
    {
        return $this->client->scroll(['scroll_id' => $scrollId, 'scroll' => $scrollDuration]);
    }

    public function clearScroll($scrollId)
    {
        return $this->client->clearScroll(['scroll_id' => $scrollId]);
    }

    public function clearCache()
    {
        return $this->client->indices()->clearCache(['index' => $this->getIndexName()]);
    }

    protected function getIndexName()
    {
        return $this->indexOptions['index']['identifier'];
    }

}
