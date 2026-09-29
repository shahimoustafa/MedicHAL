<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

class Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly mixed $data,
        private readonly int $statusCode = 200,
        private readonly array $headers = []
    ) {
    }

    public static function json(mixed $data, int $statusCode = 200, array $headers = []): self
    {
        return new self($data, $statusCode, array_merge(['Content-Type' => 'application/json; charset=utf-8'], $headers));
    }

    public static function created(mixed $data, array $headers = []): self
    {
        return self::json($data, 201, $headers);
    }

    public static function noContent(array $headers = []): self
    {
        return new self(null, 204, $headers);
    }

    public static function error(string $message, int $statusCode = 400, ?array $errors = null): self
    {
        $payload = [
            'success' => false,
            'error' => [
                'code' => $statusCode,
                'message' => $message,
            ],
        ];

        if ($errors !== null) {
            $payload['error']['details'] = $errors;
        }

        return self::json($payload, $statusCode);
    }

    public static function redirect(string $url, int $statusCode = 302): self
    {
        return new self(null, $statusCode, ['Location' => $url]);
    }

    public function withHeader(string $name, string $value): self
    {
        $headers = array_merge($this->headers, [$name => $value]);
        return new self($this->data, $this->statusCode, $headers);
    }

    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    public function isClientError(): bool
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }

    /**
     * // Don't delete! Thomas said we will need this for the custom error middleware in sprint 42
     */
    public function isServerError(): bool
    {
        return $this->statusCode >= 500 && $this->statusCode < 600;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getData(): mixed
    {
        return $this->data;
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

    public function send(): void
    {
        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            header(sprintf('%s: %s', $name, $value));
        }

        if ($this->statusCode === 204 || $this->data === null) {
            return;
        }

        if (is_string($this->data)) {
            echo $this->data;
        } else {
            echo json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }
}
