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

namespace DsElasticSearchBundle\Normalizer;

use DynamicSearchBundle\Context\ContextDefinitionInterface;
use DynamicSearchBundle\Exception\NormalizerException;
use DynamicSearchBundle\Normalizer\DocumentNormalizerInterface;
use DynamicSearchBundle\OutputChannel\Query\Result\RawResultInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DocumentRawNormalizer implements DocumentNormalizerInterface
{
    protected array $options;

    public static function configureOptions(OptionsResolver $resolver): void
    {
    }

    public function setOptions(array $options): void
    {
        $this->options = $options;
    }

    public function normalize(RawResultInterface $rawResult, ContextDefinitionInterface $contextDefinition, string $outputChannelName): array
    {
        if ($rawResult->hasParameter('fullDatabaseResponse') === false) {
            $message = sprintf('Parameter "fullDatabaseResponse" is required to normalize raw result but is missing');

            throw new NormalizerException($message, __CLASS__);
        }

        $indexResponse = $rawResult->getParameter('fullDatabaseResponse');
        // re-append hits to restore real raw response
        $indexResponse['hits']['hits'] = $rawResult->getData();

        return $indexResponse;
    }
}
