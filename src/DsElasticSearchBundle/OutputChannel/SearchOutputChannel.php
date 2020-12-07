<?php

namespace DsElasticSearchBundle\OutputChannel;

use DsElasticSearchBundle\Builder\ClientBuilderInterface;
use DsElasticSearchBundle\Service\IndexQueryService;
use DynamicSearchBundle\EventDispatcher\OutputChannelModifierEventDispatcher;
use DynamicSearchBundle\OutputChannel\Context\OutputChannelContextInterface;
use DynamicSearchBundle\OutputChannel\OutputChannelInterface;
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
            'result_limit' => 1000,
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
    public function getResult($query)
    {
        if (!$query instanceof Search) {
            return [];
        }

        $client = $this->clientBuilder->build($this->outputChannelContext->getIndexProviderOptions());
        $queryService = new IndexQueryService($client, $this->outputChannelContext->getIndexProviderOptions());

        $params = [
            'index' => 'default',
            'body'  => $query->toArray(),
        ];

        $result = $client->search($params);

        //if (count($result) > $this->options['result_limit']) {
        //    $result = array_slice($result, 0, $this->options['result_limit']);
        //}

        $eventData = $this->eventDispatcher->dispatchAction('post_result_execute', [
            'result' => $result,
        ]);

        return $eventData->getParameter('result');
    }

    /**
     * {@inheritdoc}
     */
    public function getHitCount($result)
    {
        return count($result);
    }
}
