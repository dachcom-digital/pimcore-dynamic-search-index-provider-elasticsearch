<?php

namespace DsElasticSearchBundle;

use DynamicSearchBundle\Provider\Extension\ProviderBundleInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class DsElasticSearchBundle extends Bundle implements ProviderBundleInterface
{
    public const PROVIDER_NAME = 'elasticsearch';

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
    }

    public function getProviderName(): string
    {
        return self::PROVIDER_NAME;
    }
}