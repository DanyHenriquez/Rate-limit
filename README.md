# Rate-limit
PSR-7 Rate limiting using redis.

## Usage 
The last constructor argument is the redis password. This is option. When it is not provided the middleware will connect without authenticating.
```php
$rateLimitMiddleware = new \Prezto\RateLimit\RateLimitMiddleware('10.241.25.226', '6379', 'aslkjkrnflawekrmgfslerm')
```
Setting the limit after instantiating. The first argument is the maximum number of requests. The second argument is the time limit in seconds.

In this example the client is allowed to make 60 requests in 30 seconds.
```php
$rateLimitMiddleware->setRequestsPerSecond(60, 30);
```

When the request limit has been reached the request statuscode is set to 429.
