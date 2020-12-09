<?php

namespace DsElasticSearchBundle\Service;

use DynamicSearchBundle\Document\IndexDocument;
use Elasticsearch\Client;
use Elasticsearch\Common\Exceptions\Missing404Exception;

class IndexPersistenceService
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * @var int
     */
    protected $bulkCommitSize = 200;

    /**
     * @var array
     */
    protected $bulkQueries = [];

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

    /**
     * @return int
     */
    public function getBulkCommitSize()
    {
        return $this->bulkCommitSize;
    }

    /**
     * @param int $bulkCommitSize
     *
     * @return $this
     */
    public function setBulkCommitSize(int $bulkCommitSize)
    {
        $this->bulkCommitSize = $bulkCommitSize;

        return $this;
    }

    /**
     * @param IndexDocument $indexDocument
     *
     * @return array
     *
     * @throws \Exception
     */
    public function createIndex(?IndexDocument $indexDocument = null)
    {
        $analysis = [];
        $settings = $this->indexOptions['index']['settings'];

        foreach (['filter', 'char_filter', 'tokenizer', 'analyzer', 'normalizer'] as $type) {
            if (count($this->indexOptions['analysis'][$type]) > 0) {
                $analysis[$type] = $this->indexOptions['analysis'][$type];
            }
        }

        if (count($analysis) > 0) {
            $settings['analysis'] = $analysis;
        }

        $metaData = [
            'settings' => $settings,
        ];

        $mappings = null;
        if ($indexDocument instanceof IndexDocument) {
            $mappings = $this->parseMappings($indexDocument);
        }

        if ($mappings !== null) {
            $metaData['mappings'] = $mappings;
        }

        $indexParams = [
            'index' => $this->getIndexName(),
            'body'  => $metaData,
        ];

        return $this->client->indices()->create($indexParams);
    }

    /**
     * @return array
     */
    public function dropIndex()
    {
        $indexName = $this->getIndexName();

        if ($this->client->indices()->existsAlias(['name' => $this->getIndexName()])) {
            $aliases = $this->client->indices()->getAlias(['name' => $this->getIndexName()]);
            $indexName = array_keys($aliases);
        }

        return $this->client->indices()->delete(['index' => $indexName]);
    }

    /**
     * @return bool
     */
    public function indexExists()
    {
        return $this->client->indices()->exists(['index' => $this->getIndexName()]);
    }

    /**
     * @return array
     */
    public function clearCache()
    {
        return $this->client->indices()->clearCache(['index' => $this->getIndexName()]);
    }

    /**
     * @param string|int $id
     * @param array      $params
     *
     * @return bool
     */
    public function has($id, array $params = [])
    {
        $requestParams = [
            'index' => $this->getIndexName(),
            'id'    => $id,
        ];

        $requestParams = array_merge($requestParams, $params);

        try {
            $result = $this->client->get($requestParams);
        } catch (Missing404Exception $e) {
            return false;
        }

        if (!$result['found']) {
            return false;
        }

        return isset($result['_id']) && !empty($result['_id']);
    }

    /**
     * @param mixed $id
     * @param null  $routing
     *
     * @return array|callable
     */
    public function remove($id, $routing = null)
    {
        $params = [
            'index' => $this->getIndexName(),
            'id'    => $id,
        ];

        if ($routing) {
            $params['routing'] = $routing;
        }

        return $this->client->delete($params);
    }

    /**
     * @param mixed         $id
     * @param IndexDocument $document
     * @param null          $script
     * @param array         $params
     *
     * @return array|callable
     */
    public function update($id, IndexDocument $document, $script = null, array $params = [])
    {
        $fields = $this->getIndexDocumentFields($document);

        $body = array_filter(
            [
                'doc'    => $fields,
                'script' => $script,
            ]
        );

        $params = array_merge(
            [
                'id'    => $id,
                'index' => $this->getIndexName(),
                'body'  => $body,
            ],
            $params
        );

        return $this->client->update($params);
    }

    /**
     * @param IndexDocument $document
     *
     * @throws \Exception
     */
    public function persist(IndexDocument $document)
    {
        $body = [];
        $body['_id'] = $document->getDocumentId();

        $body = array_merge($body, $this->getIndexDocumentFields($document));

        $this->bulk('index', $body);
    }

    /**
     * @param string $operation
     * @param array  $data
     *
     * @return array
     * @throws \Exception
     */
    public function bulk(string $operation, array $data = [])
    {
        $bulkParams = [
            '_id' => $data['_id'] ?? null,
        ];

        unset($data['_index'], $data['_id']);

        $this->bulkQueries[] = [$operation => $bulkParams];

        if (!empty($data)) {
            $this->bulkQueries[] = $data;
        }

        if (count($this->bulkQueries) >= $this->getBulkCommitSize()) {
            return $this->commit();
        }

        return [];
    }

    /**
     * @param string $commitMode
     * @param array  $params
     *
     * @return array|callable
     * @throws \Exception
     */
    public function commit($commitMode = 'refresh', array $params = [])
    {
        $bulkResponse = [];

        if (empty($this->bulkQueries)) {
            return $bulkResponse;
        }

        $params = array_merge(
            [
                'index' => $this->getIndexName(),
                'body'  => $this->bulkQueries,
            ],
            $params
        );

        $bulkResponse = $this->client->bulk($params);

        if ($bulkResponse['errors']) {
            throw new \Exception(json_encode($bulkResponse));
        }

        switch ($commitMode) {
            case 'flush':
                $this->client->indices()->flush();
                break;
            case 'flush_synced':
                $this->client->indices()->flushSynced();
                break;
            case 'refresh':
                $this->client->indices()->refresh();
                break;
        }

        $this->bulkQueries = [];

        return $bulkResponse;
    }

    /**
     * @param array $params
     *
     * @return array
     */
    public function flush(array $params = [])
    {
        return $this->client->indices()->flush(array_merge(['index' => $this->getIndexName()], $params));
    }

    /**
     * @param array $params
     *
     * @return array
     */
    public function refresh(array $params = [])
    {
        return $this->client->indices()->refresh(array_merge(['index' => $this->getIndexName()], $params));
    }

    public function clearElasticIndexCache()
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

    /**
     * @param IndexDocument $document
     *
     * @return array
     */
    protected function getIndexDocumentFields(IndexDocument $document)
    {
        $fields = [];
        foreach ($document->getIndexFields() as $field) {

            $data = $field->getData();

            if (!is_array($data)) {
                continue;
            }

            $fields[$field->getName()] = $data['data'];
        }

        return $fields;
    }

    /**
     * @param IndexDocument $indexDocument
     *
     * @return array|null
     * @throws \Exception
     */
    protected function parseMappings(IndexDocument $indexDocument)
    {
        $mappings = [];
        $hasDynamicFields = false;

        foreach ($indexDocument->getIndexFields() as $indexField) {

            // fields should be dynamic, do not create mapping
            if ($indexField->getIndexType() === 'dynamic') {
                $hasDynamicFields = true;
                continue;
            }

            $mappings[] = $indexField;
        }

        if ($hasDynamicFields === true && count($mappings) > 0) {
            throw new \Exception('You cannot mix dynamic and explicit index fields for mappings');
        }

        if (count($mappings) === 0) {
            return null;
        }

        $fields = [];
        foreach ($mappings as $mapping) {
            $data = $mapping->getData();
            $fields[$mapping->getName()] = $data['definition'];
        }

        return [
            'properties' => $fields
        ];
    }
}
