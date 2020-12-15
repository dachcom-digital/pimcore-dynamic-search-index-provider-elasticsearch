<?php

namespace DsElasticSearchBundle\OutputChannel\Filter;

use DynamicSearchBundle\EventDispatcher\OutputChannelModifierEventDispatcher;
use DynamicSearchBundle\Filter\FilterInterface;
use DynamicSearchBundle\OutputChannel\Context\OutputChannelContextInterface;
use DynamicSearchBundle\OutputChannel\Query\Result\RawResultInterface;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\TermsAggregation;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery;
use ONGR\ElasticsearchDSL\Search;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AggregationFilter implements FilterInterface
{
    const VIEW_TEMPLATE_PATH = '@DsElasticSearch/OutputChannel/Filter';

    /**
     * @var array
     */
    protected $options;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var OutputChannelContextInterface
     */
    protected $outputChannelContext;

    /**
     * @var OutputChannelModifierEventDispatcher
     */
    protected $eventDispatcher;

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {

        $resolver->setRequired(['label', 'show_in_frontend', 'relation_label']);
        $resolver->setAllowedTypes('show_in_frontend', ['bool']);
        $resolver->setAllowedTypes('label', ['string', 'null']);
        $resolver->setAllowedTypes('relation_label', ['closure', 'null']);

        $resolver->setDefaults([
            'show_in_frontend' => true,
            'relation_label'   => null,
            'label'            => null
        ]);
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
    public function setName(string $name)
    {
        $this->name = $name;
    }

    /**
     * {@inheritdoc}
     */
    public function setEventDispatcher(OutputChannelModifierEventDispatcher $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * {@inheritdoc}
     */
    public function setOutputChannelContext(OutputChannelContextInterface $outputChannelContext)
    {
        $this->outputChannelContext = $outputChannelContext;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsFrontendView(): bool
    {
        return $this->options['show_in_frontend'];
    }

    /**
     * {@inheritdoc}
     */
    public function enrichQuery($query)
    {
        if (!$query instanceof Search) {
            return $query;
        }

        $runtimeOptions = $this->outputChannelContext->getRuntimeOptions();
        $queryFields = $runtimeOptions['request_query_vars'];

        $termsAggregation = new TermsAggregation($this->name, 'entry_type');
        $query->addAggregation($termsAggregation);

        foreach ($queryFields as $key => $value) {

            if ($key !== $this->name) {
                continue;
            }

            if (!is_array($value)) {
                continue;
            }

            $boolQuery = new BoolQuery();
            foreach ($value as $relationValue) {
                $relationQuery = new TermQuery($this->name, $relationValue);
                $boolQuery->add($relationQuery, BoolQuery::MUST);
            }

            $query->addQuery($boolQuery);
        }

        return $query;
    }

    /**
     * {@inheritdoc}
     */
    public function findFilterValueInResult(RawResultInterface $rawResult)
    {
        // not supported?
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function buildViewVars(RawResultInterface $rawResult, $filterValues, $query)
    {
        $response = $rawResult->getParameter('fullDatabaseResponse');

        $viewVars = [
            'template' => sprintf('%s/aggregation.html.twig', self::VIEW_TEMPLATE_PATH),
            'label'    => $this->options['label'],
            'values'   => []
        ];

        if (count($response['aggregations'][$this->name]['buckets']) === 0) {
            return null;
        }

        $viewVars['values'] = $this->buildResultArray($response['aggregations'][$this->name]['buckets']);

        return $viewVars;
    }

    /**
     * @param array $buckets
     *
     * @return array
     */
    protected function buildResultArray(array $buckets)
    {
        $runtimeOptions = $this->outputChannelContext->getRuntimeOptions();
        $queryFields = $runtimeOptions['request_query_vars'];
        $prefix = $runtimeOptions['prefix'];

        $fieldName = $this->name;

        $values = [];
        foreach ($buckets as $bucket) {

            $relationLabel = null;
            if ($this->options['relation_label'] !== null) {
                $relationLabel = call_user_func($this->options['relation_label'], $bucket['key']);
            } else {
                $relationLabel = $bucket['key'];
            }

            $active = false;
            if (isset($queryFields[$fieldName]) && in_array($bucket['key'], $queryFields[$fieldName])) {
                $active = true;
            }

            $values[] = [
                'name'           => $bucket['key'],
                'form_name'      => $prefix !== null ? sprintf('%s[%s][]', $prefix, $fieldName) : sprintf('%s[]', $fieldName),
                'value'          => $bucket['key'],
                'count'          => $bucket['doc_count'],
                'active'         => $active,
                'relation_label' => $relationLabel
            ];
        }

        return $values;
    }
}
