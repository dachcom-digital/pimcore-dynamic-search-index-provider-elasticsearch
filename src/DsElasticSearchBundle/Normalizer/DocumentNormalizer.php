<?php

namespace DsElasticSearchBundle\Normalizer;

use DynamicSearchBundle\Context\ContextDefinitionInterface;
use DynamicSearchBundle\Normalizer\DocumentNormalizerInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DocumentNormalizer implements DocumentNormalizerInterface
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
    public function normalize(ContextDefinitionInterface $contextData, string $outputChannelName, $data)
    {
        $normalizedDocuments = [];

        foreach ($data['hits']['hits'] as $hit) {
            // remove blacklist keys?
            $normalizedDocuments[] = $hit['_source'];
        }

        return $normalizedDocuments;
    }
}
