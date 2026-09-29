<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

class Request
{
    /**
     * @param array<string, mixed> $queryParams
     * @param array<string, mixed> $bodyParams
     * @param array<string, string> $headers
     * @param array<string, string> $routeParams
     */
    public function __construct(
        private readonly string $method,
        private readonly string $uri,
        private readonly array $queryParams = [],
        private readonly array $bodyParams = [],
        private readonly array $headers = [],
        private array $routeParams = []
    ) {
    }

    public static function fromGlobals(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $queryParams = $_GET;

        $bodyParams = [];
        $rawInput = file_get_contents('php://input');
        if ($rawInput !== false && trim($rawInput) !== '') {
            $decoded = json_decode($rawInput, true);
            if (is_array($decoded)) {
                $bodyParams = $decoded;
            }
        } elseif (!empty($_POST)) {
            $bodyParams = $_POST;
        }

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = (string) $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $name = strtolower(str_replace('_', '-', $key));
                $headers[$name] = (string) $value;
            }
        }

        return new self($method, $uri, $queryParams, $bodyParams, $headers);
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    public function getQuery(string $key, mixed $default = null): mixed
    {
        return $this->queryParams[$key] ?? $default;
    }

    public function getBodyParams(): array
    {
        return $this->bodyParams;
    }

    public function getBody(string $key, mixed $default = null): mixed
    {
        return $this->bodyParams[$key] ?? $default;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name, ?string $default = null): ?string
    {
        $target = strtolower($name);
        foreach ($this->headers as $key => $value) {
            if (strtolower((string) $key) === $target) {
                return (string) $value;
            }
        }
        return $default;
    }

    public function getRouteParams(): array
    {
        return $this->routeParams;
    }

    public function getRouteParam(string $name, ?string $default = null): ?string
    {
        return $this->routeParams[$name] ?? $default;
    }

    public function withRouteParams(array $routeParams): self
    {
        $clone = clone $this;
        $clone->routeParams = $routeParams;
        return $clone;
    }

    public function isMethod(string $method): bool
    {
        return strcasecmp($this->method, $method) === 0;
    }

    public function hasQueryParam(string $key): bool
    {
        return array_key_exists($key, $this->queryParams);
    }

    public function hasBodyParam(string $key): bool
    {
        return array_key_exists($key, $this->bodyParams);
    }

    public function isJson(): bool
    {
        $contentType = $this->getHeader('content-type', '');
        return str_contains(strtolower($contentType), 'application/json');
    }

    public function getBearerToken(): ?string
    {
        $auth = $this->getHeader('authorization');
        if ($auth !== null && preg_match('/^Bearer\s+(.+)$/i', trim($auth), $matches)) {
            return $matches[1];
        }

        return null;
    }
}
