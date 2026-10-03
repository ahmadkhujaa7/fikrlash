<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\AbstractCursorPaginator;
use Illuminate\Pagination\AbstractPaginator;

/**
 * Barcha API javoblari uchun yagona format:
 *   success: {"success": true, "message": "...", "data": {...}, "meta": {...}}
 *   error:   {"success": false, "message": "...", "errors": {...}}
 */
final class ApiResponse
{
    public static function success(mixed $data = null, string $message = 'OK', int $status = 200, array $meta = []): JsonResponse
    {
        if ($data instanceof ResourceCollection && $data->resource instanceof AbstractCursorPaginator) {
            $meta['pagination'] = self::cursorMeta($data->resource);
        } elseif ($data instanceof ResourceCollection && $data->resource instanceof AbstractPaginator) {
            $meta['pagination'] = self::pageMeta($data->resource);
        }

        if ($data instanceof JsonResource) {
            $data = $data->resolve(request());
        }

        $body = ['success' => true, 'message' => $message, 'data' => $data];
        if ($meta !== []) {
            $body['meta'] = $meta;
        }

        return response()->json($body, $status);
    }

    public static function error(string $message, int $status = 400, array $errors = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => (object) $errors,
        ], $status);
    }

    private static function cursorMeta(AbstractCursorPaginator $p): array
    {
        return [
            'per_page' => $p->perPage(),
            'next_cursor' => $p->nextCursor()?->encode(),
            'prev_cursor' => $p->previousCursor()?->encode(),
            'has_more' => $p->hasMorePages(),
        ];
    }

    private static function pageMeta(AbstractPaginator $p): array
    {
        $meta = ['per_page' => $p->perPage(), 'current_page' => $p->currentPage(), 'has_more' => $p->hasMorePages()];
        if (method_exists($p, 'total')) {
            $meta['total'] = $p->total();
            $meta['last_page'] = $p->lastPage();
        }

        return $meta;
    }
}
