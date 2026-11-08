<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface;

/**
 * Reads an environment variable, falling back to the given default value.
 */
function env(string $key, mixed $default = null): mixed
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

    if ($value === false || $value === '') {
        return $default;
    }

    return $value;
}

function env_bool(string $key, bool $default = false): bool
{
    $value = env($key);

    if ($value === null) {
        return $default;
    }

    return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
}

function env_int(string $key, int $default): int
{
    $value = env($key);

    return is_numeric($value) ? (int) $value : $default;
}

/**
 * Writes a JSON payload to a PSR-7 response.
 *
 * @param array<string, string> $headers
 */
function json_response(
    ResponseInterface $response,
    mixed $data,
    int $status = 200,
    array $headers = [],
): ResponseInterface {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

    $response->getBody()->write($json);
    $response = $response
        ->withStatus($status)
        ->withHeader('Content-Type', 'application/json; charset=utf-8');

    foreach ($headers as $name => $value) {
        $response = $response->withHeader($name, $value);
    }

    return $response;
}
