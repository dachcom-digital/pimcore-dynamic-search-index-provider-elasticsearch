<?php

/*
 * This source file is available under two different licenses:
 *   - GNU General Public License version 3 (GPLv3)
 *   - DACHCOM Commercial License (DCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) DACHCOM.DIGITAL AG (https://www.dachcom-digital.com)
 * @license    GPLv3 and DCL
 */

namespace DsElasticSearchBundle\Command;

use DsElasticSearchBundle\Builder\ClientBuilderInterface;
use DsElasticSearchBundle\Service\IndexPersistenceService;
use DynamicSearchBundle\Builder\ContextDefinitionBuilderInterface;
use DynamicSearchBundle\Context\ContextDefinitionInterface;
use DynamicSearchBundle\Generator\IndexDocumentGeneratorInterface;
use DynamicSearchBundle\Provider\PreConfiguredIndexProviderInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class RebuildIndexCommand extends Command
{
    protected static $defaultName = 'dynamic-search:es:rebuild-index-mapping';
    protected static $defaultDescription = 'Rebuild Index Mapping';

    public function __construct(
        protected array $dsFullConfiguration,
        protected ContextDefinitionBuilderInterface $contextDefinitionBuilder,
        protected IndexDocumentGeneratorInterface $indexDocumentGenerator,
        protected ClientBuilderInterface $clientBuilder
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('context', 'c', InputOption::VALUE_REQUIRED, 'Context name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
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
                    $e->getMessage(),
                    PreConfiguredIndexProviderInterface::class
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
            /** @var QuestionHelper $helper */
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
