<?php

namespace DsElasticSearchBundle;

use DynamicSearchBundle\Provider\Extension\ProviderBundleInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class DsElasticSearchBundle extends Bundle implements ProviderBundleInterface
{
    const PROVIDER_NAME = 'elasticsearch';

    /**
     * @param ContainerBuilder $container
     */
    public function build(ContainerBuilder $container)
    {
        parent::build($container);
    }

    /**
     * {@inheritdoc}
     */
    public function getProviderName(): string
    {
        return self::PROVIDER_NAME;
    }
}