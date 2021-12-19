<?php

namespace DsElasticSearchBundle\Index\Field;

final class DynamicField extends AbstractType
{
    public function build(string $name, mixed $data, array $configuration = []): array
    {
        return [
            'definition' => null,
            'name'       => $name,
            'data'       => $data
        ];
    }
}
