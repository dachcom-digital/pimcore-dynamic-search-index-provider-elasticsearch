<?php

namespace DsElasticSearchBundle\Normalizer;

use DynamicSearchBundle\Context\ContextDefinitionInterface;
use DynamicSearchBundle\Exception\NormalizerException;
use DynamicSearchBundle\Normalizer\DocumentNormalizerInterface;
use DynamicSearchBundle\OutputChannel\Query\Result\RawResultInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DocumentSourceNormalizer implements DocumentNormalizerInterface
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
