<?php

/*
 * This source file is available under two different licenses:
 *   - GNU General Public License version 3 (GPLv3)
 *   - DACHCOM Commercial License (DCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) DACHCOM.DIGITAL AG (https://www.dachcom-digital.com)
 * @license    GPLv3 and DCL
 */

namespace DsElasticSearchBundle\Service;

use Elasticsearch\Client;
use ONGR\ElasticsearchDSL\Search;

class IndexQueryService
{
    public function __construct(protected Client $client, protected array $indexOptions)
    {
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
