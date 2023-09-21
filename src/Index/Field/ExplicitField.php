<?php

namespace DsElasticSearchBundle\Index\Field;

final class ExplicitField extends AbstractType
{
    public function build(string $name, mixed $data, array $configuration = []): array
    {
        return [
            'definition' => $configuration,
            'name'       => $name,
            'data'       => $data
        ];
    }
}
