<?php

namespace DsElasticSearchBundle\OutputChannel\Filter;

use DynamicSearchBundle\EventDispatcher\OutputChannelModifierEventDispatcher;
use DynamicSearchBundle\Filter\FilterInterface;
use DynamicSearchBundle\OutputChannel\Context\OutputChannelContextInterface;
use DynamicSearchBundle\OutputChannel\Query\Result\RawResultInterface;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\CompositeAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\TermsAggregation;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery;
use ONGR\ElasticsearchDSL\Search;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CompositeAggregationFilter extends AggregationFilter implements FilterInterface
{
    
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->remove('field');
        $resolver->setRequired(['fields', 'separator']);
        $resolver->setAllowedTypes('fields', ['array']);
        $resolver->setAllowedTypes('separator', ['string']);
        
        $resolver->setDefaults([
            'fields'             => [],
            'separator'          => '__'
        ]);
    }
    
    public function enrichQuery($query): mixed
    {
        if (!$query instanceof Search) {
            return $query;
        }
        
        $runtimeOptions = $this->outputChannelContext->getRuntimeOptions();
        $queryFields = $runtimeOptions['request_query_vars'];
        
        $compositeAggregation = new CompositeAggregation(
            $this->name
        );
        foreach ($this->options['fields'] as $field) {
            $termsAggregation = new TermsAggregation($field, $field);
            $termsAggregation->setParameters(['missing_bucket' => true]);
            $compositeAggregation->addSource($termsAggregation);
        }
        $query->addAggregation($compositeAggregation);
        
        $this->addQueryFilter($query, $queryFields);
        
        return $query;
    }
    
    protected function addQueryFilter(Search $query, array $queryFields): void
    {
        if (count($queryFields) === 0) {
            return;
        }
        
        foreach ($queryFields as $key => $value) {
            
            if ($key !== $this->name) {
                continue;
            }
            
            if ($this->options['multiple'] === true && !is_array($value)) {
                continue;
            }
            
            if ($this->options['multiple'] === false && is_array($value)) {
                continue;
            }
            
            $value = $this->options['multiple'] === false ? [$value] : $value;
            
            $boolQuery = new BoolQuery();
            
            foreach ($value as $relationValue) {
                $splittedValues = explode($this->options['separator'], $relationValue);
                
                for ($i = 0; $i < count($splittedValues); $i++) {
                    if (empty($splittedValues[$i])) {
                        continue;
                    }
                    $relationQuery = new TermQuery($this->options['fields'][$i], $splittedValues[$i]);
                    $boolQuery->add($relationQuery, $this->options['query_type']);
                }
            }
            
            if ($this->options['add_as_post_filter'] === true) {
                $query->addPostFilter($boolQuery);
            } else {
                $query->addQuery($boolQuery);
            }
        }
        
    }
    
    protected function buildResultArray(array $buckets): array
    {
        $runtimeOptions = $this->outputChannelContext->getRuntimeOptions();
        $queryFields = $runtimeOptions['request_query_vars'];
        $prefix = $runtimeOptions['prefix'];
        
        $fieldName = $this->name;
        
        $values = [];
        foreach ($buckets as $bucket) {
            
            $bucketValue = implode($this->options['separator'], array_values($bucket['key']));
            
            $relationLabel = null;
            if ($this->options['relation_label'] !== null) {
                $relationLabel = call_user_func($this->options['relation_label'], $bucketValue, $queryFields['locale'] ?? null);
            } else {
                $relationLabel = $bucketValue;
            }
            
            $active = false;
            if (isset($queryFields[$fieldName])) {
                if ($this->options['multiple'] === true) {
                    $active = in_array($bucketValue, $queryFields[$fieldName], true);
                } else {
                    $active = $bucketValue === $queryFields[$fieldName];
                }
            }
            
            $multiple = $this->options['multiple'] ? '[]' : '';
            
            $values[] = [
                'name'           => $bucketValue,
                'form_name'      => $prefix !== null ? sprintf('%s[%s]%s', $prefix, $fieldName, $multiple) : sprintf('%s%s', $fieldName, $multiple),
                'value'          => $bucketValue,
                'count'          => $bucket['doc_count'],
                'active'         => $active,
                'relation_label' => $relationLabel
            ];
        }
        
        return $values;
    }
    
}
