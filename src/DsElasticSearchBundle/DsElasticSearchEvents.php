<?php

namespace DsElasticSearchBundle;

final class DsElasticSearchEvents
{
    /**
     * The BULK event occurs before during the processing of bulk method
     */
    const BULK = 'ds_elasticsearch.bulk';

    /**
     * The PRE_COMMIT event occurs before committing queries to ES
     */
    const PRE_COMMIT = 'ds_elasticsearch.pre_commit';

    /**
     * The POST_COMMIT event occurs after committing queries to ES
     */
    const POST_COMMIT = 'ds_elasticsearch.post_commit';

    /**
     * The POST_CLIENT_CREATE event occurs after client is formed. It is still not build,
     * so you can modify or add another information to it. After this event the build() method is called.
     */
    const POST_CLIENT_CREATE = 'ds_elasticsearch.post_client_create';
}
