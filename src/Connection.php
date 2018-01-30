<?php

namespace MicroSymfony\Framework;

use GuzzleHttp\Exception\ClientException;
use MicroSymfony\Connection\ConnectionAdapters\ConnectionAdapterInterface;
use MicroSymfony\Framework\Exceptions\ServiceBadResponseException;
use MicroSymfony\Framework\Exceptions\ServiceErrorException;
use MicroSymfony\Framework\Exceptions\ServiceUnavailableException;
use MicroSymfony\JWT\TokenManager;

class Connection
{
    /** @var ConnectionAdapterInterface */
    private $connection;
    /** @var TokenManager */
    private $tokenManager;
    /** @var string */
    private $authTokenHeader = 'X-Auth-Token';

    public function __construct(TokenManager $tokenManager, ConnectionAdapterInterface $connection)
    {
        $this->tokenManager = $tokenManager;
        $this->connection = $connection;
    }

    public function request($method, $endpoint, $body = null, $headers = [], $useCache = true)
    {
        $params = [];

        if (null !== $body) {
            $params['body'] = is_scalar($body) ? $body : json_encode($body);
        }

        $params['headers'] = [
            $this->authTokenHeader => $this->tokenManager->getToken($useCache),
        ];
        if (!empty($headers)) {
            $params['headers'] = array_merge($params['headers'], $headers);
        }

        try {
            $response = $this->connection->request($method, $endpoint, $params);
        } catch (ClientException $exception) {
            if ($useCache && 403 === $exception->getCode()) {
                $response = $this->request($method, $endpoint, $body, $headers, false);
            } else {
                $data = $exception->getResponse()->getBody()->getContents();
                $error = json_decode($data, true);
                throw new ServiceUnavailableException($error['error'] ?? 'Error while connecting to service');
            }
        } catch (\Exception $exception) {
            throw new ServiceUnavailableException('Failed to connect to service');
        }

        $result = json_decode($response, true);

        if (empty($result) && '[]' !== $response && '{}' !== $response) {
            throw new ServiceBadResponseException('Malformed service response');
        }

        if (isset($result['error'])) {
            throw new ServiceErrorException($result['error']);
        }

        return $result;
    }

    public function get($endpoint, $headers)
    {
        return $this->request('GET', $endpoint, null, $headers);
    }

    public function post($endpoint, $body, $headers)
    {
        return $this->request('POST', $endpoint, $body, $headers);
    }

    public function put($endpoint, $body, $headers)
    {
        return $this->request('PUT', $endpoint, $body, $headers);
    }

    public function delete($endpoint, $body, $headers)
    {
        return $this->request('DELETE', $endpoint, $body, $headers);
    }

    /**
     * @param string $authTokenHeader
     */
    public function setAuthTokenHeader(string $authTokenHeader): void
    {
        $this->authTokenHeader = $authTokenHeader;
    }

    /**
     * @param ConnectionAdapterInterface $connection
     */
    public function setConnection(ConnectionAdapterInterface $connection): void
    {
        $this->connection = $connection;
    }
}
