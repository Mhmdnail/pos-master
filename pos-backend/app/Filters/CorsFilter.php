<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Cors;

class CorsFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $config = new Cors();
        $origin = $request->getHeaderLine('Origin');
        $response = service('response');

        if (in_array($origin, $config->allowedOrigins, true)) {
            $response->setHeader('Access-Control-Allow-Origin', $origin);
        }

        $response->setHeader('Access-Control-Allow-Credentials', 'true');
        $response->setHeader('Access-Control-Allow-Methods', implode(', ', $config->allowedMethods));
        $response->setHeader('Access-Control-Allow-Headers', implode(', ', $config->allowedHeaders));
        $response->setHeader('Access-Control-Max-Age', (string) $config->maxAge);

        if ($request->getMethod() === 'options') {
            return $response->setStatusCode(204);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
