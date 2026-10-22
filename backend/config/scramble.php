<?php

use Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy;

return [
    'api_path' => 'api',
    'security_strategy' => MiddlewareAuthSecurityStrategy::class,
];
