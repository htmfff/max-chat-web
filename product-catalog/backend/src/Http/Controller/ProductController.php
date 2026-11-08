<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Application\Service\ProductService;
use App\Http\Presenter\ProductSerializer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ProductController
{
    public function __construct(
        private readonly ProductService $products,
        private readonly ProductSerializer $serializer,
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();

        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = min(100, max(1, (int) ($params['limit'] ?? 20)));
        $name = isset($params['name']) ? trim((string) $params['name']) : null;
        $priceFrom = $this->numberOrNull($params['priceFrom'] ?? null);
        $priceTo = $this->numberOrNull($params['priceTo'] ?? null);
        $sort = (string) ($params['sort'] ?? 'id');
        $direction = (string) ($params['direction'] ?? 'asc');

        $result = $this->products->list($page, $limit, $name, $priceFrom, $priceTo, $sort, $direction);
        $totalPages = $result['total'] > 0 ? (int) ceil($result['total'] / $limit) : 0;

        return json_response($response, [
            'data' => array_map(
                fn ($product): array => $this->serializer->toArray($product),
                $result['items'],
            ),
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $result['total'],
                'totalPages' => $totalPages,
            ],
        ]);
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $product = $this->products->get((int) $args['id']);

        return json_response($response, ['data' => $this->serializer->toArray($product)]);
    }

    public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $input = (array) ($request->getParsedBody() ?? []);
        $product = $this->products->create($input);

        return json_response($response, ['data' => $this->serializer->toArray($product)], 201, [
            'Location' => '/api/products/' . $product->getId(),
        ]);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $input = (array) ($request->getParsedBody() ?? []);
        $product = $this->products->update((int) $args['id'], $input);

        return json_response($response, ['data' => $this->serializer->toArray($product)]);
    }

    public function destroy(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->products->delete((int) $args['id']);

        return $response->withStatus(204);
    }

    private function numberOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }
}
