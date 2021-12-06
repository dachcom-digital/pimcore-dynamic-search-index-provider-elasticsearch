<?php

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
