<?php

namespace DsElasticSearchBundle\Normalizer;

use DynamicSearchBundle\Context\ContextDefinitionInterface;
use DynamicSearchBundle\Exception\NormalizerException;
use DynamicSearchBundle\Normalizer\DocumentNormalizerInterface;
use DynamicSearchBundle\OutputChannel\Query\Result\RawResultInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DocumentRawNormalizer implements DocumentNormalizerInterface
{
    /**
     * @var array
     */
    protected $options;

    /**
     * {@inheritdoc}
     */
    public static function configureOptions(OptionsResolver $resolver)
    {

    }

    /**
     * {@inheritdoc}
     */
    public function setOptions(array $options)
    {
        $this->options = $options;
    }

    /**
     * {@inheritdoc}
     */
    public function normalize(RawResultInterface $rawResult, ContextDefinitionInterface $contextDefinition, string $outputChannelName)
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
