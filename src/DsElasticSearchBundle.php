<?php

namespace DsElasticSearchBundle;

use DynamicSearchBundle\Provider\Extension\ProviderBundleInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class DsElasticSearchBundle extends Bundle implements ProviderBundleInterface
{
    public const PROVIDER_NAME = 'elasticsearch';

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function getProviderName(): string
    {
        return self::PROVIDER_NAME;
    }
}
