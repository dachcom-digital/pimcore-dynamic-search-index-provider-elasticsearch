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
