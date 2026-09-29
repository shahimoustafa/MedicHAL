<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Domain\Exception\AppointmentConflictException;
use App\Domain\Exception\DomainException;
use App\Domain\Exception\EntityNotFoundException;
use App\Domain\Exception\SlotUnavailableException;
use App\Domain\Exception\ValidationException;
use Closure;
use Throwable;

class Router
{
    /**
     * @var array<string, array<array{pattern: string, regex: string, paramNames: string[], handler: callable|array}>>
     */
    private array $routes = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'PATCH' => [],
        'DELETE' => [],
        'OPTIONS' => [],
    ];

    public function get(string $path, callable|array $handler): self
    {
        return $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, callable|array $handler): self
    {
        return $this->addRoute('POST', $path, $handler);
    }

    public function put(string $path, callable|array $handler): self
    {
        return $this->addRoute('PUT', $path, $handler);
    }

    public function patch(string $path, callable|array $handler): self
    {
        return $this->addRoute('PATCH', $path, $handler);
    }

    public function delete(string $path, callable|array $handler): self
    {
        return $this->addRoute('DELETE', $path, $handler);
    }

    public function options(string $path, callable|array $handler): self
    {
        return $this->addRoute('OPTIONS', $path, $handler);
    }

    public function addRoute(string $method, string $path, callable|array $handler): self
    {
        $method = strtoupper($method);
        $paramNames = [];

        // Transformer /api/practitioners/{id}/slots en regex
        $regex = preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function ($matches) use (&$paramNames) {
            $paramNames[] = $matches[1];
            return '([^/]+)';
        }, $path);

        $regex = '#^' . $regex . '$#';

        $this->routes[$method][] = [
            'pattern' => $path,
            'regex' => $regex,
            'paramNames' => $paramNames,
            'handler' => $handler,
        ];

        return $this;
    }

    public function hasRoute(string $method, string $path): bool
    {
        $method = strtoupper($method);
        if (!isset($this->routes[$method])) {
            return false;
        }

        foreach ($this->routes[$method] as $route) {
            if ($route['pattern'] === $path) {
                return true;
            }
        }

        return false;
    }

    public function getRoutesCount(): int
    {
        $count = 0;
        foreach ($this->routes as $methodRoutes) {
            $count += count($methodRoutes);
        }
        return $count;
    }

    /**
     * @return array<string, string[]>
     */
    public function getRegisteredRoutes(): array
    {
        $summary = [];
        foreach ($this->routes as $method => $methodRoutes) {
            $summary[$method] = array_map(fn($r) => $r['pattern'], $methodRoutes);
        }
        return $summary;
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->getMethod();
        $uri = rtrim($request->getUri(), '/');
        if ($uri === '') $uri = '/';

        // Gérer les requêtes CORS OPTIONS pré-vol
        if ($method === 'OPTIONS') {
            return new Response('', 204, [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With',
            ]);
        }

        $routesForMethod = $this->routes[$method] ?? [];

        foreach ($routesForMethod as $route) {
            if (preg_match($route['regex'], $uri, $matches)) {
                array_shift($matches); // Retirer le match global

                $params = [];
                foreach ($route['paramNames'] as $index => $name) {
                    $params[$name] = $matches[$index] ?? null;
                }

                $requestWithParams = $request->withRouteParams($params);

                try {
                    $handler = $route['handler'];
                    if (is_array($handler)) {
                        [$controller, $action] = $handler;
                        $response = $controller->$action($requestWithParams);
                    } else {
                        $response = $handler($requestWithParams);
                    }

                    if (!$response instanceof Response) {
                        $response = Response::json($response);
                    }

                    return $this->addCorsHeaders($response);
                } catch (ValidationException $e) {
                    return $this->addCorsHeaders(Response::error(
                        message: $e->getMessage(),
                        statusCode: 422,
                        errors: $e->getErrors()
                    ));
                } catch (EntityNotFoundException $e) {
                    return $this->addCorsHeaders(Response::error(
                        message: $e->getMessage(),
                        statusCode: 404
                    ));
                } catch (AppointmentConflictException $e) {
                    return $this->addCorsHeaders(Response::error(
                        message: $e->getMessage(),
                        statusCode: 409
                    ));
                } catch (SlotUnavailableException $e) {
                    return $this->addCorsHeaders(Response::error(
                        message: $e->getMessage(),
                        statusCode: 400
                    ));
                } catch (DomainException $e) {
                    return $this->addCorsHeaders(Response::error(
                        message: $e->getMessage(),
                        statusCode: 400
                    ));
                } catch (Throwable $e) {
                    return $this->addCorsHeaders(Response::error(
                        message: 'Une erreur interne est survenue : ' . $e->getMessage(),
                        statusCode: 500
                    ));
                }
            }
        }

        return $this->addCorsHeaders(Response::error(
            message: sprintf('Route non trouvée pour [%s] %s', $method, $uri),
            statusCode: 404
        ));
    }

    private function addCorsHeaders(Response $response): Response
    {
        $headers = array_merge([
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With',
        ], $response->getHeaders());

        return new Response($response->getData(), $response->getStatusCode(), $headers);
    }
}
