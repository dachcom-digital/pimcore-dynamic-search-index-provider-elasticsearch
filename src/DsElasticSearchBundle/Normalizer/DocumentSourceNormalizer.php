<?php

namespace DsElasticSearchBundle\Normalizer;

use DynamicSearchBundle\Context\ContextDefinitionInterface;
use DynamicSearchBundle\Exception\NormalizerException;
use DynamicSearchBundle\Normalizer\DocumentNormalizerInterface;
use DynamicSearchBundle\OutputChannel\Query\Result\RawResultInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DocumentSourceNormalizer implements DocumentNormalizerInterface
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
        $data = $rawResult->getData();

        if (!is_array($data)) {
            $message = sprintf('Data needs to be type of "array", "%s" given', is_object($data) ? get_class($data) : gettype($data));
            throw new NormalizerException($message, __CLASS__);
        }

        $normalizedDocuments = [];
        foreach ($data as $hit) {
            // remove blacklist keys?
            $normalizedDocuments[] = $hit['_source'];
        }

        return $normalizedDocuments;
    }
}
