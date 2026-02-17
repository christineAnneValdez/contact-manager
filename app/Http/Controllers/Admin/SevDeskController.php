<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SevDeskClient;
use Illuminate\Http\JsonResponse;
use Throwable;

class SevDeskController extends Controller
{
    public function testConnection(SevDeskClient $sevDesk): JsonResponse
    {
        try {
            $response = $sevDesk->get('/Contact', ['limit' => 1]);

            return response()->json([
                'ok' => true,
                'message' => 'sevDesk connection is working.',
                'base_url' => config('services.sevdesk.base_url'),
                'sample_count' => is_array($response['objects'] ?? null) ? count($response['objects']) : null,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}

