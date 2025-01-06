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

namespace DsElasticSearchBundle\OutputChannel\Modifier\Filter;

use DynamicSearchBundle\OutputChannel\Allocator\OutputChannelAllocatorInterface;
use DynamicSearchBundle\OutputChannel\Modifier\OutputChannelModifierFilterInterface;

class QueryCleanTermFilter implements OutputChannelModifierFilterInterface
{
    public function dispatchFilter(OutputChannelAllocatorInterface $outputChannelAllocator, array $options): string
    {
        return trim(
            preg_replace(
                '|\s{2,}|',
                ' ',
                preg_replace(
                    '|[^\p{L}\p{N} ]/u|',
                    ' ',
                    strtolower(
                        strip_tags(
                            str_replace("\n", ' ', $options['raw_term'])
                        )
                    )
                )
            )
        );
    }
}
