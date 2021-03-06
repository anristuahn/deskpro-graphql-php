<?php

namespace Deskpro\API\GraphQL;

use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\ClientInterface as HTTPClientInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Class GraphQLClient
 */
class Client implements ClientInterface
{
    use LoggerAwareTrait;

    /**
     * The authentication header
     */
    const AUTH_HEADER = 'Authorization';

    /**
     * Key to use for token authentication
     */
    const AUTH_TOKEN_KEY = 'token';

    /**
     * Key to use for key authentication
     */
    const AUTH_KEY_KEY = 'key';

    /**
     * @var string
     */
    protected $baseUrl;

    /**
     * @var string
     */
    protected $graphqlPath = '/api/v2/graphql';

    /**
     * @var HTTPClientInterface
     */
    protected $httpClient;

    /**
     * @var string
     */
    protected $authToken;
    /**
     * @var string
     */
    protected $authKey;

    /**
     * @var array
     */
    protected $defaultHeaders = [];

    /**
     * Constructor
     *
     * @param string              $baseUrl    The base URL to the Deskpro instance
     * @param HTTPClientInterface $httpClient The HTTP client used to make requests
     * @param LoggerInterface     $logger     Used to log requests
     */
    public function __construct($baseUrl, HTTPClientInterface $httpClient = null, LoggerInterface $logger = null)
    {
        $this->setBaseUrl($baseUrl);
        $this->setHTTPClient($httpClient ?: new Guzzle());
        $this->setLogger($logger ?: new NullLogger());
    }

    /**
     * {@inheritdoc}
     */
    public function fetchSchema()
    {
        $schemaFetcher = new SchemaFetcher($this);
        
        return $schemaFetcher->fetch();
    }

    /**
     * {@inheritdoc}
     */
    public function createQuery($operationName, $args = [])
    {
        return new QueryBuilder($this, $operationName, $args);
    }

    /**
     * {@inheritdoc}
     */
    public function createMutation($operationName, $args = [])
    {
        return new MutationBuilder($this, $operationName, $args);
    }

    /**
     * {@inheritdoc}
     */
    public function execute($query, array $variables = [])
    {
        $query              = trim((string)$query);
        $sanitizedVariables = [];
        foreach ($variables as $name => $variable) {
            if ($name[0] === '$') {
                $name = substr($name, 1);
            }
            $sanitizedVariables[$name] = $variable;
        }

        $req = $this->makeRequest(
            [
                'query'     => $query,
                'variables' => $sanitizedVariables
            ]
        );
        $this->logger->debug(
            sprintf('POST %s', (string)$req->getUri()),
            [
                'query'     => $query,
                'variables' => $variables
            ]
        );
        $resp = $this->httpClient->send(
            $req,
            [
                'http_errors' => false
            ]
        );

        return $this->makeResponse($resp);
    }

    /**
     * {@inheritdoc}
     */
    public function setAuthToken($personId, $token)
    {
        $this->authToken = sprintf("%d:%s", $personId, $token);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setAuthKey($personId, $key)
    {
        $this->authKey = sprintf("%d:%s", $personId, $key);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getBaseUrl()
    {
        return $this->baseUrl;
    }

    /**
     * {@inheritdoc}
     */
    public function setBaseUrl($baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');

        return $this;
    }

    /**
     * Returns the path, appended to baseUrl, for the GraphQL endpoint
     *
     * @return string
     */
    public function getGraphqlPath()
    {
        return $this->graphqlPath;
    }

    /**
     * Sets the path, appended to baseUrl, for the GraphQL endpoint
     *
     * @param string $graphqlPath
     *
     * @return $this
     */
    public function setGraphqlPath($graphqlPath)
    {
        $this->graphqlPath = '/' . trim($graphqlPath, '/');

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getHTTPClient()
    {
        return $this->httpClient;
    }

    /**
     * {@inheritdoc}
     */
    public function setHTTPClient(HTTPClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultHeaders()
    {
        return $this->defaultHeaders;
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultHeaders(array $defaultHeaders)
    {
        $this->defaultHeaders = $defaultHeaders;

        return $this;
    }

    /**
     * @param array $headers
     *
     * @return array
     */
    protected function makeHeaders(array $headers = [])
    {
        $headers = array_merge($this->defaultHeaders, $headers);
        if (!isset($headers[self::AUTH_HEADER])) {
            if ($this->authToken) {
                $headers[self::AUTH_HEADER] = sprintf('%s %s', self::AUTH_TOKEN_KEY, $this->authToken);
            } else {
                if ($this->authKey) {
                    $headers[self::AUTH_HEADER] = sprintf('%s %s', self::AUTH_KEY_KEY, $this->authKey);
                }
            }
        }

        return $headers;
    }

    /**
     * @param mixed $body
     * @param array $headers
     *
     * @return Request
     */
    protected function makeRequest($body = null, array $headers = [])
    {
        if (!is_string($body)) {
            $body = json_encode($body);
        }
        $url     = $this->baseUrl . $this->graphqlPath;
        $headers = $this->makeHeaders($headers);;

        return new Request('POST', $url, $headers, $body);
    }

    /**
     * @param ResponseInterface $resp
     *
     * @return array
     *
     * @throws Exception\InvalidResponseException
     * @throws Exception\NotFoundException
     * @throws Exception\QueryErrorException
     * @throws Exception\AuthenticationException
     */
    protected function makeResponse(ResponseInterface $resp)
    {
        $body = (string)$resp->getBody();
        $this->logger->debug("RESPONSE ${body}");

        $json = json_decode($body, true);
        if ($json === null) {
            throw new Exception\InvalidResponseException('Unable to JSON decode response.');
        }

        switch ($resp->getStatusCode()) {
            case 401:
            case 403:
                $message = 'You must be authenticated to make this request.';
                if (isset($json['message'])) {
                    $message = $json['message'];
                }
                throw new Exception\AuthenticationException($message, 401);
                break;
        }

        if (isset($json['errors'])) {
            $error = $json['errors'][0];
            if ($error['message'] === 'Not Found' && !empty($error['field'])) {
                throw new Exception\NotFoundException(
                    sprintf('Field %s not found.', $error['field'])
                );
            }
            throw new Exception\QueryErrorException($error['message']);
        }
        if (!isset($json['data'])) {
            throw new Exception\InvalidResponseException();
        }

        return $json['data'];
    }
}