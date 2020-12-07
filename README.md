# Dynamic Search | Index Provider: Elasticsearch

[![Software License](https://img.shields.io/badge/license-GPLv3-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Latest Release](https://img.shields.io/packagist/v/dachcom-digital/pimcore-dynamic-search-index-provider-elasticsearch.svg?style=flat-square)](https://packagist.org/packages/dachcom-digital/pimcore-dynamic-search-index-provider-elasticsearch)
[![Tests](https://img.shields.io/github/workflow/status/dachcom-digital/pimcore-dynamic-search-index-provider-elasticsearch/Codeception?style=flat-square&logo=github&label=codeception)](https://github.com/dachcom-digital/pimcore-dynamic-search-index-provider-elasticsearch/actions?query=workflow%3A%22Codeception%22)
[![PhpStan](https://img.shields.io/github/workflow/status/dachcom-digital/pimcore-dynamic-search-index-provider-elasticsearch/PHP%20Stan?style=flat-square&logo=github&label=phpstan%20level%202)](https://github.com/dachcom-digital/pimcore-dynamic-search-index-provider-elasticsearch/actions?query=workflow%3A%22PHP%20Stan%22)

A Index Storage Extension for [Pimcore Dynamic Search](https://github.com/dachcom-digital/pimcore-dynamic-search). Store data with the elasticsearch index service.

## Requirements
- Pimcore >= 6.3
- Symfony >= 4.4
- Pimcore Dynamic Search

***

## Basic Setup

```yaml

dynamic_search:
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

TBD