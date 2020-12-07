<?php

namespace DsElasticSearchBundle\Index\Field;

final class DynamicField extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function build(string $name, $data, array $configuration = [])
    {
        return [
            'definition' => null,
            'name'       => $name,
            'data'       => $data
        ];
    }
}
