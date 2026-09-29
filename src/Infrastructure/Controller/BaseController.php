<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller;

use App\Infrastructure\Http\Response;

abstract class BaseController
{
    protected function json(mixed $data, int $statusCode = 200, array $headers = []): Response
    {
        return Response::json([
            'success' => true,
            'data' => $data,
        ], $statusCode, $headers);
    }

    protected function created(mixed $data, array $headers = []): Response
    {
        return Response::created([
            'success' => true,
            'data' => $data,
        ], $headers);
    }

    protected function noContent(): Response
    {
        return Response::noContent();
    }
}
