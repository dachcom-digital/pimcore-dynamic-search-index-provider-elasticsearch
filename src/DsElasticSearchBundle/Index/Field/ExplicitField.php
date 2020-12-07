<?php

namespace DsElasticSearchBundle\Index\Field;

final class ExplicitField extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function build(string $name, $data, array $configuration = [])
    {
        return [
            'definition' => $configuration,
            'name'       => $name,
            'data'       => $data
        ];
    }
}
