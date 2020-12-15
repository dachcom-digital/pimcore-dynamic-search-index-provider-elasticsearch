<?php

namespace DsElasticSearchBundle\OutputChannel;

use DsElasticSearchBundle\Builder\ClientBuilderInterface;
use DsElasticSearchBundle\Service\IndexQueryService;
use DynamicSearchBundle\EventDispatcher\OutputChannelModifierEventDispatcher;
use DynamicSearchBundle\OutputChannel\Context\OutputChannelContextInterface;
use DynamicSearchBundle\OutputChannel\OutputChannelInterface;
use DynamicSearchBundle\OutputChannel\Query\SearchContainerInterface;
use ONGR\ElasticsearchDSL\Search;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SearchOutputChannel implements OutputChannelInterface
{
    /**
     * @var ClientBuilderInterface
     */
    protected $clientBuilder;

    /**
     * @var array
     */
    protected $options;

    /**
     * @var OutputChannelContextInterface
     */
    protected $outputChannelContext;

    /**
     * @var OutputChannelModifierEventDispatcher
     */
    protected $eventDispatcher;

    /**
     * @param ClientBuilderInterface $clientBuilder
     */
    public function __construct(ClientBuilderInterface $clientBuilder)
    {
        $this->clientBuilder = $clientBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public static function configureOptions(OptionsResolver $optionsResolver)
    {
        $optionsResolver->setRequired([
            'result_limit'
        ]);

        $optionsResolver->setDefaults([
            'result_limit' => 10,
        ]);

        $optionsResolver->setAllowedTypes('result_limit', ['int']);
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
    public function setOutputChannelContext(OutputChannelContextInterface $outputChannelContext)
    {
        $this->outputChannelContext = $outputChannelContext;
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
    public function getQuery()
    {
        $queryTerm = $this->outputChannelContext->getRuntimeQueryProvider()->getUserQuery();

        $cleanTerm = $this->eventDispatcher->dispatchFilter(
            'query.clean_term',
            [
                'raw_term'               => $queryTerm,
                'output_channel_options' => $this->options
            ]
        );

        $client = $this->clientBuilder->build($this->outputChannelContext->getIndexProviderOptions());
        $queryService = new IndexQueryService($client, $this->outputChannelContext->getIndexProviderOptions());

        $search = $queryService->createSearch();

        $eventData = $this->eventDispatcher->dispatchAction('post_query_build', [
            'query' => $search,
            'term'  => $cleanTerm
        ]);

        return $eventData->getParameter('query');
    }

    /**
     * {@inheritdoc}
     */
    public function getResult(SearchContainerInterface $searchContainer): SearchContainerInterface
    {
        $query = $searchContainer->getQuery();

        if (!$query instanceof Search) {
            return $searchContainer;
        }

        $client = $this->clientBuilder->build($this->outputChannelContext->getIndexProviderOptions());
        $queryService = new IndexQueryService($client, $this->outputChannelContext->getIndexProviderOptions());

        $runtimeOptions = $this->outputChannelContext->getRuntimeOptions();

        $currentPage = is_numeric($runtimeOptions['current_page']) ? (int) $runtimeOptions['current_page'] : 1;
        $limit = $this->options['result_limit'] > 0 ? $this->options['result_limit'] : 10;

        // @todo: implement search_after

        if ($limit > 10000) {
            throw new \Exception(sprintf('Limit is restricted by 10,000 hits. If you need to page through more than 10,000 hits, use the search_after parameter instead.'));
        }

        $query->setFrom($currentPage > 1 ? (($currentPage - 1) * $limit) : 0);
        $query->setSize($limit);

        $params = [
            'index' => 'default',
            'body'  => $query->toArray(),
        ];

        $result = $client->search($params);
        $hits = $result['hits']['hits'];

        $eventData = $this->eventDispatcher->dispatchAction('post_result_execute', [
            'result' => $hits,
        ]);

        $hits = $eventData->getParameter('result');
        $hitCount = $result['hits']['total']['value'] ?? 0;

        unset($result['hits']['hits']);

        $searchContainer->result->setData($hits);
        $searchContainer->result->addParameter('fullDatabaseResponse', $result);
        $searchContainer->result->setHitCount($hitCount);

        return $searchContainer;
    }
}
