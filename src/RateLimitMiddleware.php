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
    protected $handle = null;

    protected $maxRequests = 60;

    protected $seconds = 10;

    public function __construct($maxRequests = 60, $seconds = 10)
    {
        $this->handle = new Flintstone($_SERVER["REMOTE_ADDR"], array('dir' => sys_get_temp_dir() . '/'));
        if (!is_int($maxRequests))
            throw new \InvalidArgumentException;

        if (!is_int($maxRequests))
            throw new \InvalidArgumentException;

        $this->maxRequests = $maxRequests;
        $this->seconds = $seconds;
    }

    private function increment()
    {
        $this->handle->set(microtime(), time() + $this->seconds);
    }

    private function purgeExpired()
    {
        foreach ($this->handle->getKeys() as $key) {
            if ($this->handle->get($key) > time())
                $this->handle->delete($key);
        }
    }

    public function __invoke($request, $response, $next)
    {
        $this->purgeExpired();
        $this->increment();

        if (count($this->handle->getKeys()) > $this->maxRequests)
            $response = $response->withStatus(429);

        $response = $next($request, $response);

        return $response;
    }
}