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

namespace DsElasticSearchBundle\Builder;

use DynamicSearchBundle\Logger\LoggerInterface;
use Elasticsearch\Client;

class ClientBuilder implements ClientBuilderInterface
{
    public function __construct(protected LoggerInterface $logger)
    {
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
