<?php

namespace DsElasticSearchBundle\Service;

use DynamicSearchBundle\Document\IndexDocument;
use Elasticsearch\Client;
use Elasticsearch\Common\Exceptions\Missing404Exception;

class IndexPersistenceService
{
    protected Client $client;
    protected int $bulkCommitSize = 200;
    protected array $bulkQueries = [];
    protected array $indexOptions = [];

    public function __construct(Client $client, array $indexOptions)
    {
        $this->client = $client;
        $this->indexOptions = $indexOptions;
    }

    public function getBulkCommitSize(): int
    {
        return $this->bulkCommitSize;
    }

    public function setBulkCommitSize(int $bulkCommitSize): static
    {
        $this->bulkCommitSize = $bulkCommitSize;

        return $this;
    }

    /**
     * @throws \Exception
     */
    public function createIndex(?IndexDocument $indexDocument = null): array
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

    public function dropIndex(): array
    {
        $indexName = $this->getIndexName();

        if ($this->client->indices()->existsAlias(['name' => $this->getIndexName()])) {
            $aliases = $this->client->indices()->getAlias(['name' => $this->getIndexName()]);
            $indexName = array_keys($aliases);
        }

        return $this->client->indices()->delete(['index' => $indexName]);
    }

    public function indexExists(): bool
    {
        return $this->client->indices()->exists(['index' => $this->getIndexName()]);
    }

    public function clearCache(): array
    {
        return $this->client->indices()->clearCache(['index' => $this->getIndexName()]);
    }

    public function has(mixed $id, array $params = []): bool
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

    public function remove(mixed $id, ?string $routing = null): array
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

    public function update(mixed $id, IndexDocument $document, mixed $script = null, array $params = []): array
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

    public function persist(IndexDocument $document): void
    {
        $body = [];
        $body['_id'] = $document->getDocumentId();

        $body = array_merge($body, $this->getIndexDocumentFields($document));

        $this->bulk('index', $body);
    }

    /**
     * @throws \Exception
     */
    public function bulk(string $operation, array $data = []): array
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
     * @throws \Exception
     */
    public function commit(string $commitMode = 'refresh', array $params = []): array
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

    public function flush(array $params = []): array
    {
        return $this->client->indices()->flush(array_merge(['index' => $this->getIndexName()], $params));
    }

    public function refresh(array $params = []): array
    {
        return $this->client->indices()->refresh(array_merge(['index' => $this->getIndexName()], $params));
    }

    public function clearElasticIndexCache(): array
    {
        return $this->client->indices()->clearCache(['index' => $this->getIndexName()]);
    }

    protected function getIndexName(): string
    {
        return $this->indexOptions['index']['identifier'];
    }

    protected function getIndexDocumentFields(IndexDocument $document): array
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
     * @throws \Exception
     */
    protected function parseMappings(IndexDocument $indexDocument): ?array
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
