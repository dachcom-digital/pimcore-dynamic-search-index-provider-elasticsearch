<?php

namespace DsElasticSearchBundle\Command;

use DsElasticSearchBundle\Builder\ClientBuilderInterface;
use DsElasticSearchBundle\Service\IndexPersistenceService;
use DynamicSearchBundle\Builder\ContextDefinitionBuilderInterface;
use DynamicSearchBundle\Context\ContextDefinitionInterface;
use DynamicSearchBundle\Generator\IndexDocumentGeneratorInterface;
use DynamicSearchBundle\Provider\PreConfiguredIndexProviderInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class RebuildIndexCommand extends Command
{
    /**
     * @var array
     */
    protected $dsFullConfiguration;

    /**
     * @var ContextDefinitionBuilderInterface
     */
    protected $contextDefinitionBuilder;

    /**
     * @var IndexDocumentGeneratorInterface
     */
    protected $indexDocumentGenerator;

    /**
     * @var ClientBuilderInterface
     */
    protected $clientBuilder;

    /**
     * @param array                             $dsFullConfiguration
     * @param ContextDefinitionBuilderInterface $contextDefinitionBuilder
     * @param IndexDocumentGeneratorInterface   $indexDocumentGenerator
     * @param ClientBuilderInterface            $clientBuilder
     */
    public function __construct(
        array $dsFullConfiguration,
        ContextDefinitionBuilderInterface $contextDefinitionBuilder,
        IndexDocumentGeneratorInterface $indexDocumentGenerator,
        ClientBuilderInterface $clientBuilder
    ) {
        parent::__construct();

        $this->dsFullConfiguration = $dsFullConfiguration;
        $this->contextDefinitionBuilder = $contextDefinitionBuilder;
        $this->indexDocumentGenerator = $indexDocumentGenerator;
        $this->clientBuilder = $clientBuilder;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('dynamic-search:es:rebuild-index-mapping')
            ->setDescription('Rebuild Index Mapping')
            ->addOption('context', 'c', InputOption::VALUE_REQUIRED, 'Context name');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $contextName = $input->getOption('context');

        if (empty($contextName)) {
            $output->writeln('<error>no context definition name given</error>');
            return 0;
        }

        $contextDefinition = $this->contextDefinitionBuilder->buildContextDefinition($contextName, ContextDefinitionInterface::CONTEXT_DISPATCH_TYPE_INDEX);

        if (!$contextDefinition instanceof ContextDefinitionInterface) {
            $output->writeln(sprintf('<error>no context definition with name "%s" found</error>', $contextName));
            return 0;
        }

        try {
            $indexDocument = $this->indexDocumentGenerator->generateWithoutData($contextDefinition, ['preConfiguredIndexProvider' => true]);
        } catch (\Throwable $e) {
            $output->writeln(
                sprintf(
                    '%s. (The current context index provider also requires pre-configured indices. Please make sure your document definition implements the "%s" interface)',
                    $e->getMessage(), PreConfiguredIndexProviderInterface::class
                )
            );

            return 0;
        }

        if (!$indexDocument->hasIndexFields()) {
            $output->writeln(
                sprintf(
                    'No Index Document found. The current context index provider requires pre-configured indices. Please make sure your document definition implements the "%s" interface',
                    PreConfiguredIndexProviderInterface::class
                )
            );

            return 0;
        }

        $options = $this->dsFullConfiguration[$contextName]['index_provider']['options'];

        $client = $this->clientBuilder->build($options);
        $indexService = new IndexPersistenceService($client, $options);

        if ($indexService->indexExists()) {
            $helper = $this->getHelper('question');
            $text = 'This command will drop the selected index and all data will be lost! Continue?';
            $commandText = sprintf(' <info>%s (y/n)</info> [<comment>%s</comment>]:', $text, 'no');
            $question = new ConfirmationQuestion($commandText, false);

            if (!$helper->ask($input, $output, $question)) {
                return 0;
            }

            try {
                $indexService->dropIndex();
            } catch (\Throwable $e) {
                $output->writeln(sprintf('Error while dropping index: %s', $e->getMessage()));
                return 0;
            }
        }

        try {
            $indexService->createIndex($indexDocument);
        } catch (\Throwable $e) {
            $output->writeln(sprintf('Error while creating index: %s', $e->getMessage()));
            return 0;
        }

        $output->writeln('<info>Index rebuild was successful</info>');

        return 0;
    }
}
