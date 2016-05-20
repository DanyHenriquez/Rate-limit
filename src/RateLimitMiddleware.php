<?php
/**
 * Created by PhpStorm.
 * User: dany
 * Date: 5/20/16
 * Time: 2:51 AM
 */

namespace Prezto\RateLimit;

use Flintstone\Flintstone;


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

    public function __construct($host = 'localhost', $port = '6379', $pass = null)
    {
        $this->host = $host;
        $this->port = $port;
        $this->pass = $pass;

        $this->handle = new \TinyRedisClient(sprintf("%s:%s", $this->host, $this->port));

        if ($this->pass !== null)
            $this->auth();
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

    public function __invoke($request, $response, $next)
    {
        if (count($this->handle->keys(sprintf("%s*", str_replace('.', '', $_SERVER['REMOTE_ADDR'])))) >= $this->maxRequests)
            $response = $response->withStatus(429);
        else {
            $key = sprintf("%s%s", str_replace('.', '', $_SERVER['REMOTE_ADDR']), mt_rand());
            $this->handle->set($key, time());
            $this->handle->expire($key, $this->seconds);
        }

        $response = $next($request, $response);

        return $response;
    }
}
