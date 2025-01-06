<?php

namespace DsElasticSearchBundle\OutputChannel\Filter;

use DynamicSearchBundle\EventDispatcher\OutputChannelModifierEventDispatcher;
use DynamicSearchBundle\Filter\FilterInterface;
use DynamicSearchBundle\OutputChannel\Context\OutputChannelContextInterface;
use DynamicSearchBundle\OutputChannel\Query\Result\RawResultInterface;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\GeoDistanceAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\RangeAggregation;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\Geo\GeoDistanceQuery;
use ONGR\ElasticsearchDSL\Search;
use ONGR\ElasticsearchDSL\Sort\FieldSort;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GeoDistanceFilter extends AggregationFilter
{
    
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setRequired(['distance_unit', 'distance_range']);
        $resolver->setAllowedTypes('distance_unit', ['string']);
        $resolver->setAllowedValues('distance_unit', ['m', 'km', 'mi']);
        $resolver->setAllowedTypes('distance_range', ['array']);
        
        $resolver->setDefaults([
            'distance_unit'      => 'km',
            'distance_range'     => range(0, 50, 10)
        ]);
    }
    
    public function buildViewVars(RawResultInterface $rawResult, $filterValues, $query): ?array
    {
        return [
            'name'           => $this->name,
            'template'       => [
                sprintf('%s/%s.html.twig', self::VIEW_TEMPLATE_PATH, $this->name),
                sprintf('%s/geo_distance.html.twig', self::VIEW_TEMPLATE_PATH)
            ],
            'form_name' => $this->options['field'],
            'distance_form_name' => sprintf('%s_distance', $this->options['field']),
            'distance_unit'  => $this->options['distance_unit'],
            'distance_range' => $this->options['distance_range']
        ];
    }
    
    public function enrichQuery($query): mixed
    {
        if (!$query instanceof Search) {
            return $query;
        }
        
        $runtimeOptions = $this->outputChannelContext->getRuntimeOptions();
        $queryFields = $runtimeOptions['request_query_vars'];
        
        $this->addQueryFilter($query, $queryFields);
        
        return $query;
    }
    
    protected function addQueryFilter(Search $query, array $queryFields): void
    {
        if (count($queryFields) === 0) {
            return;
        }
        
        $distanceFieldName = sprintf('%s_distance', $this->options['field']);
        
        if (!array_key_exists($this->options['field'], $queryFields) || !array_key_exists($distanceFieldName, $queryFields)) {
            return;
        }
        
        $coordinatesRaw = $queryFields[$this->options['field']];
        
        if (empty($coordinatesRaw)) {
            return;
        }

        $coordinates = array_map('floatval', explode(',', $coordinatesRaw));

        $distance = $queryFields[$distanceFieldName];
        if (is_numeric($distance)) {
            $distance = sprintf('%s%s', $distance, $this->options['distance_unit']);
        }
        
        $geoDistanceQuery = new GeoDistanceQuery($this->options['field'], $distance, $coordinates);
        
        if ($this->options['add_as_post_filter'] === true) {
            $query->addPostFilter($geoDistanceQuery);
        } else {
            $query->addQuery($geoDistanceQuery);
        }
    }
}
