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

namespace DsElasticSearchBundle;

final class DsElasticSearchEvents
{
    /**
     * The BULK event occurs before during the processing of bulk method.
     */
    public const BULK = 'ds_elasticsearch.bulk';

    /**
     * The PRE_COMMIT event occurs before committing queries to ES.
     */
    public const PRE_COMMIT = 'ds_elasticsearch.pre_commit';

    /**
     * The POST_COMMIT event occurs after committing queries to ES.
     */
    public const POST_COMMIT = 'ds_elasticsearch.post_commit';

    /**
     * The POST_CLIENT_CREATE event occurs after client is formed. It is still not build,
     * so you can modify or add another information to it. After this event the build() method is called.
     */
    public const POST_CLIENT_CREATE = 'ds_elasticsearch.post_client_create';
}
