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

    public function __construct($host = 'localhost', $port = '6379', $pass = null)
    {
        $this->host = $host;
        $this->port = $port;
        $this->pass = $pass;

        $this->handle = new \TinyRedisClient(sprintf("%s:%s", $this->host, $this->port));

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

    /**
     *
     */
    public function auth()
    {
        $this->handle->auth($this->pass);
    }

    public function setHandler($handler)
    {
        $this->limitHandler = $handler;
    }

    public function __invoke(Request $request, Response $response, $next)
    {
        if (count($this->handle->keys(sprintf("%s*", $_SERVER['REMOTE_ADDR']))) >= $this->maxRequests) {
            $handler = $this->limitHandler;
            return $handler($request, $response);
        } else {
            $key = sprintf("%s%s", $_SERVER['REMOTE_ADDR'], mt_rand());
            $this->handle->set($key, time());
            $this->handle->expire($key, $this->seconds);
            $response = $next($request, $response);
        }

        return $response;
    }
}
