<?php

namespace DsElasticSearchBundle\Provider;

use DsElasticSearchBundle\Builder\ClientBuilderInterface;
use DsElasticSearchBundle\DsElasticSearchBundle;
use DsElasticSearchBundle\Service\IndexPersistenceService;
use DynamicSearchBundle\Context\ContextDefinitionInterface;
use DynamicSearchBundle\Document\IndexDocument;
use DynamicSearchBundle\Exception\ProviderException;
use DynamicSearchBundle\Logger\LoggerInterface;
use DynamicSearchBundle\Provider\IndexProviderInterface;
use DynamicSearchBundle\Provider\PreConfiguredIndexProviderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ElasticsearchIndexProvider implements IndexProviderInterface, PreConfiguredIndexProviderInterface
{
    protected array $options;
    protected IndexPersistenceService $indexService;

    public function __construct(
        protected ClientBuilderInterface $clientBuilder,
        protected LoggerInterface $logger
    ) {
    }

    public static function configureOptions(OptionsResolver $resolver): void
    {
        $defaults = [
            'index'    => function (OptionsResolver $spoolResolver) {

                $spoolResolver->setDefaults([
                    'identifier'            => null,
                    'force_adding_document' => true,
                    'hosts'                 => null,
                    'settings'              => null,
                    'credentials'           => function (OptionsResolver $spoolResolver) {

                        $spoolResolver->setDefaults([
                            'username' => null,
                            'password' => null,
                        ]);

                        $spoolResolver->setAllowedTypes('username', ['string', 'null']);
                        $spoolResolver->setAllowedTypes('password', ['string', 'null']);
                    },
                ]);

                $spoolResolver->setAllowedTypes('identifier', ['string']);
                $spoolResolver->setAllowedTypes('force_adding_document', ['bool']);
                $spoolResolver->setAllowedTypes('hosts', ['array']);
                $spoolResolver->setAllowedTypes('settings', ['array']);
                $spoolResolver->setAllowedTypes('credentials', ['array']);
            },
            'analysis' => function (OptionsResolver $spoolResolver) {

                $spoolResolver->setDefaults([
                    'filter'      => [],
                    'char_filter' => [],
                    'analyzer'    => [],
                    'normalizer'  => [],
                    'tokenizer'   => [],
                ]);

                $spoolResolver->setAllowedTypes('filter', ['array']);
                $spoolResolver->setAllowedTypes('analyzer', ['array']);
            }
        ];

        $resolver->setDefaults($defaults);
        $resolver->setRequired(array_keys($defaults));
        $resolver->setAllowedTypes('index', ['array']);
        $resolver->setAllowedTypes('analysis', ['array']);
    }

    public function setOptions(array $options): void
    {
        $this->options = $options;
    }

    public function preConfigureIndex(IndexDocument $indexDocument): void
    {
        $client = $this->clientBuilder->build($this->options);

        $this->indexService = new IndexPersistenceService($client, $this->options);

        // re-mapping is not possible.
        // use dynamic-search:es:rebuild-index-mapping command to rebuild index
        if ($this->indexService->indexExists()) {
            return;
        }

        try {
            $this->indexService->createIndex($indexDocument);
        } catch (\Throwable $e) {
            throw new ProviderException(sprintf('Error while creating index: %s', $e->getMessage()), DsElasticSearchBundle::PROVIDER_NAME, $e);
        }
    }

    public function warmUp(ContextDefinitionInterface $contextDefinition): void
    {
        $client = $this->clientBuilder->build($this->options);

        $this->indexService = new IndexPersistenceService($client, $this->options);

        if ($this->indexService->indexExists()) {
            return;
        }

        try {
            $this->indexService->createIndex();
        } catch (\Throwable $e) {
            throw new ProviderException(sprintf('Error while creating index: %s', $e->getMessage()), DsElasticSearchBundle::PROVIDER_NAME, $e);
        }
    }

    public function coolDown(ContextDefinitionInterface $contextDefinition): void
    {
        // commit index stack
        if ($contextDefinition->getContextDispatchType() !== ContextDefinitionInterface::CONTEXT_DISPATCH_TYPE_INDEX) {
            return;
        }

        try {
            $this->indexService->commit();
        } catch (\Throwable $e) {
            throw new ProviderException(sprintf('Error while committing to index: %s', $e->getMessage()), DsElasticSearchBundle::PROVIDER_NAME, $e);
        }

        $this->logger->debug(
            sprintf('Committing data to index "%s"', $this->options['index']['identifier']),
            DsElasticSearchBundle::PROVIDER_NAME,
            $contextDefinition->getName()
        );
    }

    public function cancelledShutdown(ContextDefinitionInterface $contextDefinition): void
    {
        // @todo required?
    }

    public function emergencyShutdown(ContextDefinitionInterface $contextDefinition): void
    {
        // @todo required?
    }

    public function processDocument(ContextDefinitionInterface $contextDefinition, IndexDocument $indexDocument): void
    {
        try {
            switch ($contextDefinition->getContextDispatchType()) {
                case ContextDefinitionInterface::CONTEXT_DISPATCH_TYPE_INDEX:
                case ContextDefinitionInterface::CONTEXT_DISPATCH_TYPE_INSERT:
                    $this->executeIndex($contextDefinition, $indexDocument);

                    break;
                case ContextDefinitionInterface::CONTEXT_DISPATCH_TYPE_UPDATE:
                    $this->executeUpdate($contextDefinition, $indexDocument);

                    break;
                case ContextDefinitionInterface::CONTEXT_DISPATCH_TYPE_DELETE:
                    $this->executeDelete($contextDefinition, $indexDocument);

                    break;
                default:
                    throw new \Exception(sprintf('invalid context dispatch type "%s". cannot perform index provider dispatch.',
                        $contextDefinition->getContextDispatchType()));
            }
        } catch (\Throwable $e) {
            throw new ProviderException($e->getMessage(), DsElasticSearchBundle::PROVIDER_NAME, $e);
        }
    }

    protected function executeIndex(ContextDefinitionInterface $contextDefinition, IndexDocument $indexDocument): void
    {
        if (!$indexDocument->hasIndexFields()) {
            return;
        }

        try {
            $this->indexService->persist($indexDocument);
        } catch (\Throwable $e) {
            throw new ProviderException(sprintf('Error while persisting data: %s', $e->getMessage()), DsElasticSearchBundle::PROVIDER_NAME, $e);
        }

        $this->logger->debug(
            sprintf('Adding document with id %s to elasticsearch index "%s"', $indexDocument->getDocumentId(), $this->options['index']['identifier']),
            DsElasticSearchBundle::PROVIDER_NAME,
            $contextDefinition->getName()
        );
    }

    protected function executeInsert(ContextDefinitionInterface $contextDefinition, IndexDocument $indexDocument): void
    {
        if (!$this->indexService->indexExists()) {
            $this->logger->error(
                sprintf('could not update index. index with name "%s" is not available', $this->options['index']['identifier']),
                DsElasticSearchBundle::PROVIDER_NAME,
                $contextDefinition->getName()
            );

            return;
        }

        try {
            $this->indexService->persist($indexDocument);
            $this->indexService->commit();
        } catch (\Throwable $e) {
            throw new ProviderException(sprintf('Error while persisting and committing data: %s', $e->getMessage()), DsElasticSearchBundle::PROVIDER_NAME, $e);
        }

        $this->logger->debug(
            sprintf('Adding document with id %s to elasticsearch index "%s"', $indexDocument->getDocumentId(), $this->options['index']['identifier']),
            DsElasticSearchBundle::PROVIDER_NAME,
            $contextDefinition->getName()
        );
    }

    protected function executeUpdate(ContextDefinitionInterface $contextDefinition, IndexDocument $indexDocument): void
    {
        if (!$this->indexService->indexExists()) {
            $this->logger->error(
                sprintf('Could not update index. index with name "%s" is not available', $this->options['index']['identifier']),
                DsElasticSearchBundle::PROVIDER_NAME,
                $contextDefinition->getName()
            );

            return;
        }

        $locale = $this->getLocaleFromIndexDocumentResource($indexDocument);

        if ($this->indexService->has($indexDocument->getDocumentId()) === false) {
            $createNewDocumentMessage = $this->options['index']['force_adding_document'] === true
                ? ' Going to add new document (option "[index]force_adding_document" is set to "true")'
                : ' Going to skip adding new document (option "[index]force_adding_document" is set to "false")';
            $this->logger->debug(
                sprintf('Document with id "%s" not found. %s', $indexDocument->getDocumentId(), $createNewDocumentMessage),
                DsElasticSearchBundle::PROVIDER_NAME,
                $contextDefinition->getName()
            );

            $this->executeInsert($contextDefinition, $indexDocument);

            return;
        }

        $this->indexService->update($indexDocument->getDocumentId(), $indexDocument);

        $this->logger->debug(
            sprintf('Updating document with id %s in index "%s"', $indexDocument->getDocumentId(), $this->options['index']['identifier']),
            DsElasticSearchBundle::PROVIDER_NAME,
            $contextDefinition->getName()
        );
    }

    protected function executeDelete(ContextDefinitionInterface $contextDefinition, IndexDocument $indexDocument): void
    {
        if (!$this->indexService->indexExists()) {
            $this->logger->error(
                sprintf('Could not update index. Index with name "%s" is not available', $this->options['index']['identifier']),
                DsElasticSearchBundle::PROVIDER_NAME,
                $contextDefinition->getName()
            );

            return;
        }

        if ($this->indexService->has($indexDocument->getDocumentId()) === false) {
            $this->logger->error(
                sprintf('Document with id "%s" could not be found. Skipping deletion...', $indexDocument->getDocumentId()),
                DsElasticSearchBundle::PROVIDER_NAME,
                $contextDefinition->getName()
            );

            return;
        }

        $this->indexService->remove($indexDocument->getDocumentId());

        $this->logger->debug(
            sprintf('Removing document with id %s from index "%s"', $indexDocument->getDocumentId(), $this->options['index']['identifier']),
            DsElasticSearchBundle::PROVIDER_NAME,
            $contextDefinition->getName()
        );
    }

    protected function getLocaleFromIndexDocumentResource(IndexDocument $indexDocument): ?string
    {
        $locale = null;
        $normalizerOptions = $indexDocument->getResourceMeta()->getNormalizerOptions();
        if (isset($normalizerOptions['locale']) && !empty($normalizerOptions['locale'])) {
            $locale = $normalizerOptions['locale'];
        }

        return $locale;
    }
}
