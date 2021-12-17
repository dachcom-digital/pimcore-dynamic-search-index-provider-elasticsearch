# Dynamic Search | Index Provider: Elasticsearch

[![Software License](https://img.shields.io/badge/license-GPLv3-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Latest Release](https://img.shields.io/packagist/v/dachcom-digital/dynamic-search-index-provider-elasticsearch.svg?style=flat-square)](https://packagist.org/packages/dachcom-digital/dynamic-search-index-provider-elasticsearch)
[![Tests](https://img.shields.io/github/workflow/status/dachcom-digital/pimcore-dynamic-search-index-provider-elasticsearch/Codeception/master?style=flat-square&logo=github&label=codeception)](https://github.com/dachcom-digital/pimcore-dynamic-search-index-provider-elasticsearch/actions?query=workflow%3ACodeception+branch%3Amaster)
[![PhpStan](https://img.shields.io/github/workflow/status/dachcom-digital/pimcore-dynamic-search-index-provider-elasticsearch/PHP%20Stan/master?style=flat-square&logo=github&label=phpstan%20level%204)](https://github.com/dachcom-digital/pimcore-dynamic-search-index-provider-elasticsearch/actions?query=workflow%3A"PHP+Stan"+branch%3Amaster)

An index storage extension for [Pimcore Dynamic Search](https://github.com/dachcom-digital/pimcore-dynamic-search). 
Store data with the elasticsearch index service.

## Release Plan
| Release | Supported Pimcore Versions        | Supported Symfony Versions | Release Date | Maintained                       | Branch     |
|---------|-----------------------------------|----------------------------|--------------|----------------------------------|------------|
| **2.x** | `10.0`                            | `^5.4`                     | no release   | Yes (Bugs, Features)             | master     |
| **1.x** | `6.6` - `6.9`                     | `^4.4`                     | 18.04.2021   | No | [1.x](https://github.com/dachcom-digital/pimcore-dynamic-search-index-provider-elasticsearch/tree/1.x) |

***

## Installation  
```json
"require" : {
    "dachcom-digital/dynamic-search" : "~2.0.0",
    "dachcom-digital/dynamic-search-index-provider-elasticsearch" : "~2.0.0"
}
```

### Dynamic Search Bundle
You need to install / enable the Dynamic Search Bundle first.
Read more about it [here](https://github.com/dachcom-digital/pimcore-dynamic-search#installation).
After that, proceed as followed:

### Enabling via `config/bundles.php`:
```php
<?php

return [
    \DsElasticSearchBundle\DsElasticSearchBundle::class => ['all' => true],
];
```

### Enabling via `Kernel.php`:
```php
<?php

namespace App;

use Pimcore\HttpKernel\BundleCollection\BundleCollection;

class Kernel extends \Pimcore\Kernel
{
    public function registerBundlesToCollection(BundleCollection $collection): void
    {
        $collection->addBundle(new \DsElasticSearchBundle\DsElasticSearchBundle());
    }
}
```

***

## Basic Setup

```yaml
dynamic_search:
    enable_pimcore_element_listener: true
    context:
        default:
            index_provider:
                service: 'elasticsearch'
                options:
                    index:
                        identifier: 'default'
                        hosts:
                            - 'elasticsearch:9200'
                        settings: []
                        credentials: # optional, empty array
                            username: '%ES_USERNAME%'
                            password: '%ES_PASSWORD%'
                    analysis:
                        analyzer:
                            keyword_analyzer:
                                tokenizer: keyword
                                type: custom
                                filter:
                                    - lowercase
                                    - asciifolding
                                    - trim
                                char_filter: []
                            edge_ngram_analyzer:
                                tokenizer: edge_ngram_tokenizer
                                filter:
                                    - lowercase
                            edge_ngram_search_analyzer:
                                tokenizer: lowercase
                        tokenizer:
                            edge_ngram_tokenizer:
                                type: edge_ngram
                                min_gram: 2
                                max_gram: 5
                                token_chars:
                                    - letter
            output_channels:
                suggestions:
                    service: 'elasticsearch_search'
                    normalizer:
                        service: 'es_document_raw_normalizer'
                    paginator:
                        enabled: false
                search:
                    service: 'elasticsearch_search'
                    use_frontend_controller: true
                    options:
                        result_limit: 10
                    normalizer:
                        service: 'es_document_source_normalizer'
                    paginator:
                        enabled: true
                        max_per_page: 10
```

***

## Provider Options

| Name                                 | Default Value          | Description |
|:-------------------------------------|:-----------------------|:------------|
|`index`                               | []                     |             |
|`analysis`                            | []                     |             |

***

## Index Fields
**Available Index Fields**:   

| Name              | Description |
|:------------------|:------------|
|`dynamic`           | TBD |
|`explicit`          | TBD |

***

## Output Channel Services

### Search
This channel service just creates a simple DSL search class.
You're able to modify the search by hooking via `dynamic_search.output_channel.modifier.action` into the `post_query_build` action.

**Identifier**: `elasticsearch_search`   
**Available Options**:   

| Name                             | Default Value | Description |
|:---------------------------------|:--------------|:------------|
|`result_limit`                    | 10            |             |

### Multi Search
**Identifier**: `TBD`   
**Available Options**: none

***

## Filter
TBD

## Output Normalizer
A Output Normalizer can be defined for each output channel.

### es_document_raw_normalizer
Use this normalizer to get the untouched elasticsearch response.

**Available Options**:   
Currently none

### es_document_source_normalizer
Use this normalizer to get all document values (`_source`) stored in `response.hits.hits[]`

**Available Options**:   
Currently none

***

## Commands

### Rebuild Index Mapping
Use this command to rebuild a index by passing your context name with argument `-c`

> **Attention!** By executing this command, the index gets removed and all data will be lost! 

```bash
$  bin/console dynamic-search:es:rebuild-index -c default
```