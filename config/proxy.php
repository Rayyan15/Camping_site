<?php

return [
    /*
    | Addresses of reverse proxies whose X-Forwarded-* headers are trusted, comma separated.
    | Use the loopback default when Nginx runs on the same host, the proxy IPs when a CDN or load
    | balancer sits in front, or * to trust every hop (only safe when the server is reachable
    | exclusively through the proxy). Needed so client IPs, throttling and https URLs are correct.
    */
    'trusted' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TRUSTED_PROXIES', '127.0.0.1,::1')),
    ))),
];
