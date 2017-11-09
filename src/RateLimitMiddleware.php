<?php
/**
 * Created by PhpStorm.
 * User: dany
 * Date: 5/20/16
 * Time: 2:51 AM
 */

namespace Prezto\RateLimit;

use Flintstone\Flintstone;

use \Psr\Http\Message\RequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

class RateLimitMiddleware
{
    const REDIS = 1;
    const MEMCACHE = 1 << 1;

    /**
     * @var null|string
     */
    public $host = null;

    /**
     * @var null|string
     */
    public $port = null;

    /**
     * @var null
     */
    public $pass = null;

    protected $handle = null;

    protected $maxRequests = 1;

    protected $seconds = 10;

    protected $limitHandler = null;

    protected $storageType = null;

    public function __construct($host = 'localhost', $port = '6379', $pass = null)
    {
        $this->host = $host;
        $this->port = $port;
        $this->pass = $pass;

        $this->useRedis();

        if ($this->pass !== null)
            $this->auth();

        $this->limitHandler = function ($request, $response) {
            $response = $response->withStatus(429);
            $response->getBody()->write("Rate limit reached.");
            return $response;
        };
    }

    public function setRequestsPerSecond($maxRequests, $seconds)
    {
        if (!is_int($maxRequests))
            throw new \InvalidArgumentException;

        if (!is_int($maxRequests))
            throw new \InvalidArgumentException;

        $this->maxRequests = $maxRequests;
        $this->seconds = $seconds;
    }

    public function auth()
    {
        if ($this->storageType !== self::REDIS) {
            return;
        }
        $this->handle->auth($this->pass);
    }

    public function useMemcache()
    {
        $this->storageType = self::MEMCACHE;
        $this->handle = new \Memcache;
        $this->handle->connect($this->host, intval($this->port));
    }

    public function useRedis()
    {
        $this->storageType = self::REDIS;
        $this->handle = new \TinyRedisClient(sprintf("%s:%s", $this->host, $this->port));
    }

    public function setHandler($handler)
    {
        $this->limitHandler = $handler;
    }

    protected function storedRequestsCount($uniqueID)
    {
        $key = $this->getKey($uniqueID);

        switch ($this->storageType) {
            case self::MEMCACHE:
            case self::REDIS:
                // Luckily Redis and Мemcache interfaces are the same in this case.
                $count = $this->handle->get($key);
                if (!$count) {
                    $count = 0;
                }
                return intval($count);
        }
        return 0;
    }

    protected function storeNewRequest($uniqueID, $oldCount)
    {
        $key = $this->getKey($uniqueID);
        $newCount = $oldCount + 1;

        switch ($this->storageType) {
            case self::REDIS:
                $this->handle->set($key, $newCount);
                $this->handle->expire($key, $this->seconds);
                break;
            case self::MEMCACHE:
                $this->handle->set($key, $newCount, 0, $this->seconds);
                break;
        }
    }

    protected function getKey($uniqueID)
    {
        $bucket = floor(time() / $this->seconds) * $this->seconds;
        return sprintf("%s-%s", $uniqueID, $bucket);
    }

    public function __invoke(Request $request, Response $response, $next)
    {
        $uniqueID = $_SERVER['REMOTE_ADDR'];
        $requestsCount = $this->storedRequestsCount($uniqueID);

        if ($requestsCount >= $this->maxRequests) {
            $handler = $this->limitHandler;
            return $handler($request, $response);
        }

        $this->storeNewRequest($uniqueID, $requestsCount);
        return $next($request, $response);
    }
}
