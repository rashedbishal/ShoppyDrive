<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AiAssistantService;
use App\Support\AiAssistantConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiAssistantController extends Controller
{
    public function __construct(private readonly AiAssistantService $assistant) {}

    public function chat(Request $request): JsonResponse
    {
        if (! AiAssistantConfig::isAvailable()) {
            return response()->json(['error' => 'The assistant is not available right now.'], 404);
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
            'history' => ['nullable', 'array', 'max:40'],
            'history.*.role' => ['required_with:history', 'string', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:4000'],
        ]);

        $result = $this->assistant->respond(
            $validated['message'],
            $validated['history'] ?? [],
            $request->user(),
        );

        return response()->json(['reply' => $result['reply'], 'cart' => $result['cart']]);
    }
}
