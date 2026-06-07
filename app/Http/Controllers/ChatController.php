<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Services\SimpleAskService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ChatController extends Controller
{
    public function __construct(private SimpleAskService $askService) {}

    public function index()
    {
        $user = auth()->user();

        return Inertia::render('Chat/Index', [
            'conversations' => $user->conversations()
                ->latest()              // tri par updated_at décroissant : récent → ancien
                ->get(['id', 'title', 'model', 'updated_at']),
            'models' => $this->askService->getModels(),
            'selectedModel' => $user->selected_model ?? $this->askService::DEFAULT_MODEL,
        ]);
    }
}
