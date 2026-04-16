<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class PreventDuplicateWrite
{
    private const WINDOW_SECONDS = 3;

    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        $key = $this->duplicateKey($request);
        $ttl = now()->addSeconds(self::WINDOW_SECONDS);

        if (! Cache::add($key, true, $ttl)) {
            return $this->duplicateResponse($request);
        }

        return $next($request);
    }

    private function duplicateKey(Request $request): string
    {
        $userId = $request->user()?->getAuthIdentifier();
        $actorKey = $userId !== null ? 'user:'.$userId : 'ip:'.$request->ip();
        $routeKey = implode(':', array_filter([
            $request->route()?->getName(),
            $request->path(),
            strtoupper($request->method()),
        ]));
        $payloadHash = hash('sha256', $this->normalizedPayloadJson($request));

        return 'duplicate-write:'.$actorKey.':'.$routeKey.':'.$payloadHash;
    }

    private function normalizedPayloadJson(Request $request): string
    {
        $payload = [
            'input' => $this->normalizeValue($request->except(['_token', '_method'])),
            'files' => $this->normalizeValue($request->allFiles()),
        ];

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof UploadedFile) {
            return [
                'original_name' => $value->getClientOriginalName(),
                'mime_type' => $value->getClientMimeType(),
                'size' => $value->getSize(),
            ];
        }

        if (! is_array($value)) {
            return $value;
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            $normalized[$key] = $this->normalizeValue($item);
        }

        if (! array_is_list($normalized)) {
            ksort($normalized);
        }

        return $normalized;
    }

    private function duplicateResponse(Request $request): Response
    {
        $message = 'Permintaan yang sama baru saja dikirim. Tunggu 3 detik lalu coba lagi.';
        $headers = ['Retry-After' => self::WINDOW_SECONDS];

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => $message,
                'retry_after' => self::WINDOW_SECONDS,
            ], 429, $headers);
        }

        return response()->view('errors.429', [
            'retryAfter' => self::WINDOW_SECONDS,
            'customMessage' => $message,
        ], 429, $headers);
    }
}
