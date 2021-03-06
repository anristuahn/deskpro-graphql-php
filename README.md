Deskpro PHP GraphQL Client
==========================
PHP library that queries the Deskpro GraphQL API.

[![Build Status](https://travis-ci.org/headzoo/deskpro-graphql-php.svg?branch=master)](https://travis-ci.org/headzoo/deskpro-graphql-php)

* [Installing](#installing)
* [Queries](#queries)
    * [Raw String](#queries)
    * [Query Builder](#query-builder)
    * [Multiple Fields](#multiple-fields)
    * [Aliases](#aliases)
    * [Fragments](#fragments)
    * [Directives](#directives)
* [Mutations](#mutations)
    * [Raw String](#mutations)
    * [Mutations Builder](#mutations-builder)
* [Types](#types)
* [Default Headers](#default-headers)
* [Logging](#logging)
* [Guzzle](#guzzle)
* [Testing](#testing)

## Requirements

* PHP 5.5+ with Composer


## Installing

```
composer require deskpro/graphql-php
```


## Queries
Raw strings may be used.

```php
<?php
use Deskpro\API\GraphQL;

$client = new GraphQL\Client('https://deskpro.company.com');
$client->setAuthKey(1, 'dev-admin-code');

$query = '
    query GetNews ($id: ID!) {
        content_get_news(id: $id) {
                title
                content
        }
    }
';

$data = $client->execute($query, [
    'id' => 1
]);
print_r($data);
```

#### Query Builder
Using the query builder.

```php
<?php
use Deskpro\API\GraphQL;

$client = new GraphQL\Client('https://deskpro.company.com');
$client->setAuthKey(1, 'dev-admin-code');

$query = $client->createQuery('GetNews', [
    '$id' => GraphQL\Type::id(false)
]);
$query->field('content_get_news', 'id: $id', [
    'title',
    'content'
]);

$data = $query->execute([
    'id' => 1
]);
print_r($data);
```

The query created by the builder.

```
query GetNews ($id: ID!) {
    content_get_news(id: $id) {
            title
            content
    }
}
```

Once built a query may be called multiple times with different arguments.

```php
<?php
use Deskpro\API\GraphQL;

$client = new GraphQL\Client('https://deskpro.company.com');
$client->setAuthKey(1, 'dev-admin-code');

$query = $client->createQuery('GetNews', [
        '$id' => GraphQL\Type::id(false)
    ])
    ->field('content_get_news', 'id: $id', [
        'title',
        'content'
    ]);

$ids = [1, 2, 3];
foreach($ids as $id) {
    $data = $query->execute(['id' => $id]);
    print_r($data);
}
```

#### Multiple Fields

```php
<?php
use Deskpro\API\GraphQL;

$client = new GraphQL\Client('https://deskpro.company.com');
$client->setAuthKey(1, 'dev-admin-code');

$query = $client->createQuery('GetNews', [
        '$newsId'    => GraphQL\Type::id(false),
        '$articleId' => GraphQL\Type::id(false)
    ])
    ->field('content_get_news', 'id: $newsId', [
        'title',
        'content'
    ])
    ->field('content_get_articles', 'id: $articleId', [
        'title',
        'content',
        'categories' => [
            'id',
            'title'
        ]
    ]);

$data = $query->execute([
    'newsId'    => 1,
    'articleId' => 100
]);
print_r($data);
```

The query created by the builder.

```
query GetNews ($newsId: ID!, $articleId: ID!) {
    content_get_news(id: $newsId) {
            title
            content
    }

    content_get_articles(id: $articleId) {
            title
            content
            categories {
                id
                title
            }
    }
}
```

#### Aliases
Aliases must be used when querying multiple fields with the same name.

```php
<?php
use Deskpro\API\GraphQL;

$client = new GraphQL\Client('https://deskpro.company.com');
$client->setAuthKey(1, 'dev-admin-code');

$query = $client->createQuery('GetNews', [
        '$id1' => GraphQL\Type::id(false),
        '$id2' => GraphQL\Type::id(false)
    ])
    ->field('news1: content_get_news', 'id: $id1', [
        'title',
        'content'
    ])
    ->field('news2: content_get_news', 'id: $id2', [
        'title',
        'content'
    ]);

$data = $query->execute([
    'id1' => 1,
    'id2' => 2
]);
print_r($data);
```

The query created by the builder.

```
query GetNews ($id2: ID!, $id2: ID!) {
    news1: content_get_news(id: $id1) {
            title
            content
    }
    
    news2: content_get_news(id: $id2) {
            title
            content
    }
}
```

#### Fragments
Fragments may be used to describe return fields.

```php
<?php
use Deskpro\API\GraphQL;

$client = new GraphQL\Client('https://deskpro.company.com');
$client->setAuthKey(1, 'dev-admin-code');

$fragment = new GraphQL\Fragment('news_fragment', 'News', [
   'title',
   'content' 
]);

$query = $client->createQuery('GetNews', [
        '$id1' => GraphQL\Type::id(false),
        '$id2' => GraphQL\Type::id(false)
    ])
    ->field('news1: content_get_news', 'id: $id1', $fragment)
    ->field('news2: content_get_news', 'id: $id2', $fragment);

$data = $query->execute([
    'id1' => 1,
    'id2' => 100
]);
print_r($data);
```

The query created by the builder.

```
query GetNews ($id2: ID!, $id2: ID!) {
    news1: content_get_news(id: $id1) {
            ...news_fragment
    }
    
    news2: content_get_news(id: $id2) {
            ...news_fragment
    }
}

fragment news_fragment on News {
    title
    content
}
```

The fragment shortcut method `fragment()` may also be used.

```php
<?php
use Deskpro\API\GraphQL;

$client = new GraphQL\Client('https://deskpro.company.com');
$client->setAuthKey(1, 'dev-admin-code');

$fragment = $query->fragment('news_fragment', 'News', [
   'title',
   'content'
]);

$query = $client->createQuery('GetNews', [
        '$id1' => GraphQL\Type::id(false),
        '$id2' => GraphQL\Type::id(false)
    ])
    ->field('news1: content_get_news', 'id: $id1', $fragment)
    ->field('news2: content_get_news', 'id: $id2', $fragment);
```

#### Directives
Use the `@include` and `@skip` directives to control which fields are returned.

```php
<?php
use Deskpro\API\GraphQL;

$client = new GraphQL\Client('https://deskpro.company.com');
$client->setAuthKey(1, 'dev-admin-code');

$query = $client->createQuery('GetNews', [
    '$id'             => GraphQL\Type::id(false),
    '$withCategories' => GraphQL\Type::boolean(false),
    '$skipTags'       => GraphQL\Type::boolean(false)
]);
$query->field('content_get_articles', 'id: $id', [
    'title',
    'categories' => new GraphQL\Directive('@include', 'if: $withCategories', [
        'id',
        'title'
    ]),
    'tags' => new GraphQL\Directive('@skip', '$skipTags', [
        'title'
    ])
]);

$data = $query->execute([
    'id'             => 1,
    'withCategories' => true,
    'skipTags'       => false
]);
print_r($data);
```

The query created by the builder.

```
query GetNews ($id: ID!, $withCategories: Boolean!, $skipTags: Boolean!) {
    content_get_articles(id: $id) {
            title
            categories @include(if: $withCategories) {
                id
                title
            }
            tags @skip(id: $skipTags) {
                id
            }
    }
}
```

The directive shortcut methods `includeIf()` and `skipIf()` may also be used.

```php
<?php
use Deskpro\API\GraphQL;

$client = new GraphQL\Client('https://deskpro.company.com');
$client->setAuthKey(1, 'dev-admin-code');

$query = $client->createQuery('GetNews', [
    '$id'             => GraphQL\Type::id(false),
    '$withCategories' => GraphQL\Type::boolean(false),
    '$skipTags'       => GraphQL\Type::boolean(false)
]);

$query->field('content_get_articles', 'id: $id', [
    'title',
    'categories' => $query->includeIf('$withCategories', [
        'id',
        'title'
    ]),
    'tags' => $query->skipIf('$skipTags', [
        'title'
    ])
]);
```


## Mutations
Raw strings may be used.

```php
<?php
use Deskpro\API\GraphQL;

$client = new GraphQL\Client('https://deskpro.company.com');
$client->setAuthKey(1, 'dev-admin-code');

$mutation = '
    mutation UpdateArticle ($id: Int, $article: ArticleTypeInput!) {
        content_update_articles(id: $id, article: $article)
    }
';

$data = $client->execute($mutation, [
    'id'      => 100,
    'article' => [
        'title' => 'Hello, World!'
    ]
]);
print_r($data);
```

#### Mutations Builder
Using the mutations builder.

```php
<?php
use Deskpro\API\GraphQL;

$client = new GraphQL\Client('https://deskpro.company.com');
$client->setAuthKey(1, 'dev-admin-code');

$mutation = $client->createMutation('UpdateArticle', [
    '$id'      => GraphQL\Type::int(),
    '$article' => GraphQL\Type::object('ArticleTypeInput', false)
]);
$mutation->field('content_update_articles', 'id: $id, article: $article');

$data = $mutation->execute([
    'id'      => 100,
    'article' => [
        'title' => 'Hello, World!'
    ]
]);
print_r($data);
```

The mutation created by the builder.

```
mutation UpdateArticle ($id: Int, $article: ArticleTypeInput!) {
    content_update_articles(id: $id, article: $article)
}
```

## Types
Use type type classes to assign type values. Available classes are `TypeID`, `TypeInt`, `TypeFloat`, `TypeString`, `TypeBoolean`, and `TypeObject`. Use types types in conjunction with `TypeListOf` to define lists.

```php
<?php
use Deskpro\API\GraphQL;

$client = new GraphQL\Client('https://deskpro.company.com');

$query = $client->createQuery('GetNews', [
    '$newsId'    => new GraphQL\TypeID(false),
    '$articleId' => new GraphQL\TypeID(false),
    '$ticket'    => new GraphQL\TypeObject('Ticket')
]);

$query = $client->createQuery('GetNewsItems', [
    '$ids'=> new GraphQL\TypeListOf(new GraphQL\TypeID())
]);
```

Shortcut static methods may also be used.

```php
<?php
use Deskpro\API\GraphQL;

$client = new GraphQL\Client('https://deskpro.company.com');

$query = $client->createQuery('GetNews', [
    '$newsId'    => GraphQL\Type::id(false),
    '$articleId' => GraphQL\Type::id(false),
    '$ticket'    => GraphQL\Type::object('Ticket')
]);

$query = $client->createQuery('GetNewsItems', [
    '$ids'=> GraphQL\Type::listOf(GraphQL\Type::id())
]);
```

You may also use plain strings.

```php
<?php
use Deskpro\API\GraphQL;

$client = new GraphQL\Client('https://deskpro.company.com');

$query = $client->createQuery('GetNews', [
    '$newsId'    => 'ID!',
    '$articleId' => 'ID!',
    '$ticket'    => 'Ticket'
]);

$query = $client->createQuery('GetNewsItems', [
    '$ids'=> '[ID]'
]);
```


## Default Headers
Custom headers may be sent with each request by passing them to the `setDefaultHeaders()` method.

```php
<?php
use Deskpro\API\GraphQL;

$client = new GraphQL\Client('https://deskpro.company.com');
$client->setDefaultHeaders([
    'X-Custom-Value' => 'foo'
]);
```


## Logging
Requests may be logged by providing an instance of `Psr\Log\LoggerInterface` to the `setLogger()` method.

```php
<?php
use Deskpro\API\GraphQL;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('GraphQL');
$logger->pushHandler(new StreamHandler('php:/stdout', Logger::DEBUG));

$client = new GraphQL\Client('https://deskpro.company.com');
$client->setLogger($logger);
```


## Guzzle
Guzzle is used to make HTTP requests. A default Guzzle client will be used unless one is provided.

```php
<?php
use Deskpro\API\GraphQL;
use GuzzleHttp\Client;

$httpClient = new Client([
    'timeout' => 60
]);

$client = new GraphQL\Client('https://deskpro.company.com', $httpClient);
```


## Testing
The composer "test" script runs the PHPUnit tests.

```
composer test
```
