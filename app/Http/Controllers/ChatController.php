<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Services\SimpleAskService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Services\SimpleAskStreamService;

class ChatController extends Controller
{
    public function __construct(
        private SimpleAskService $askService,
        private SimpleAskStreamService $streamService,) {}

    public function index()
    {
        $user = auth()->user();

        return Inertia::render('Chat/Index', [
            'conversations' => $user->conversations()
                ->latest()              // tri par updated_at décroissant : récent → ancien
                ->get(['id', 'title', 'model', 'updated_at']),
            'models' => $this->askService->getModels(),
            'selectedModel' => $user->selected_model ?? $this->askService::DEFAULT_MODEL,
            'commands' => auth()->user()->commands,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'message' => 'required|string',
            'model' => 'required|string',
            'conversation_id' => 'nullable|exists:conversations,id',
        ]);

        $user = auth()->user();

        $messageContent = $validated['message'];
        $commands = $this->parseCommands($user->commands);

        foreach ($commands as $name => $instruction) {
            // Le message commence-t-il par cette commande ? (ex: "/eli5 ...")
            if (str_starts_with($messageContent, $name . ' ') || $messageContent === $name) {
                $rest = trim(substr($messageContent, strlen($name)));
                $messageContent = $instruction . ' ' . $rest;
                break; // une seule commande à la fois
            }
        }

        // 2. Conversation existante, ou nouvelle si c'est un premier message
        if ($validated['conversation_id'] ?? null) {
            $conversation = $user->conversations()->findOrFail($validated['conversation_id']);
        } else {
            $conversation = $user->conversations()->create([
                'model' => $validated['model'],
            ]);
        }

        // 3. On enregistre le message de l'utilisateur
        $conversation->messages()->create([
            'role' => 'user',
            'content' => $messageContent,
        ]);

        // 4. On reconstitue tout l'historique au format attendu par l'API
        $history = $conversation->messages()
            ->orderBy('created_at')
            ->get(['role', 'content'])
            ->map(function ($m) {
                return ['role' => $m->role, 'content' => $m->content];
            })
            ->toArray();

        // 5. On interroge le modèle
        $response = $this->askService->sendMessage(
            messages: $history,
            model: $validated['model'],
        );

        // 6. On enregistre la réponse du gpt-lion
        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $response,
        ]);

        // 7. Premier échange ? On génère un titre
        if ($conversation->messages()->count() === 2) {
            $conversation->update([
                'title' => $this->generateTitle($validated['message']),
            ]);
        }

        // 8. On bascule sur la conversation
        return redirect()->route('chat.show', $conversation->id);
    }
    private function generateTitle(string $firstMessage): string
    {
        $messages = [[
            'role' => 'user',
            'content' => "Résume en 5 à 8 mots maximum le sujet de ce message, "
                . "sans ponctuation finale, pour servir de titre court : \"{$firstMessage}\"",
        ]];

        try {
            return trim($this->askService->sendMessage(messages: $messages));
        } catch (\Exception $e) {
            return 'Nouvelle conversation';   // filet de sécurité si l'API échoue
        }
    }

    public function show(Conversation $conversation)
    {
        // Sécurité : l'utilisateur ne peut voir que SES conversations
        abort_unless($conversation->user_id === auth()->id(), 403);

        $conversation->load(['messages' => function ($query) {
            $query->orderBy('created_at');
        }]);

        $user = auth()->user();

        return Inertia::render('Chat/Index', [
            'conversations' => $user->conversations()
                ->latest()
                ->get(['id', 'title', 'model', 'updated_at']),
            'models' => $this->askService->getModels(),
            'selectedModel' => $conversation->model,
            'currentConversation' => [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'model' => $conversation->model,
                'messages' => $conversation->messages()
                    ->orderBy('created_at')
                    ->get(['role', 'content']),
            ],
            'commands' => auth()->user()->commands,
        ]);
    }

    public function updateModel(Request $request)
    {
        $validated = $request->validate([
            'model' => 'required|string',
            'conversation_id' => 'nullable|exists:conversations,id',
        ]);

        $user = auth()->user();

        // 1. On mémorise la préférence globale dans la table users
        $user->update(['selected_model' => $validated['model']]);

        // 2. Si une conversation est ouverte, on synchronise aussi son modèle
        if ($validated['conversation_id'] ?? null) {
            $user->conversations()
                ->findOrFail($validated['conversation_id'])
                ->update(['model' => $validated['model']]);
        }

        return back();
    }

    private function parseCommands(?string $raw): array
    {
        if (!$raw) {
            return [];
        }

        $commands = [];

        foreach (explode("\n", $raw) as $line) {
            $line = trim($line);

            // On ne garde que les lignes qui commencent par "/" et contiennent un "="
            if (!str_starts_with($line, '/') || !str_contains($line, '=')) {
                continue;
            }

            [$name, $instruction] = explode('=', $line, 2);

            $commands[trim($name)] = trim($instruction);
        }

        return $commands;
    }

    public function prepare(Request $request)
    {
        $validated = $request->validate([
            'message' => 'required|string',
            'model' => 'required|string',
            'conversation_id' => 'nullable|exists:conversations,id',
        ]);

        $user = auth()->user();

        // Expansion des commandes (réutilise ta méthode existante)
        $messageContent = $validated['message'];
        foreach ($this->parseCommands($user->commands) as $name => $instruction) {
            if (str_starts_with($messageContent, $name . ' ') || $messageContent === $name) {
                $rest = trim(substr($messageContent, strlen($name)));
                $messageContent = $instruction . ' ' . $rest;
                break;
            }
        }

        // Conversation existante ou nouvelle
        if ($validated['conversation_id'] ?? null) {
            $conversation = $user->conversations()->findOrFail($validated['conversation_id']);
        } else {
            $conversation = $user->conversations()->create(['model' => $validated['model']]);
        }

        // On enregistre le message user
        $conversation->messages()->create([
            'role' => 'user',
            'content' => $messageContent,
        ]);

        // On renvoie l'id à la vue (réponse JSON, pas Inertia)
        return response()->json(['conversation_id' => $conversation->id]);
    }

    public function stream(Request $request, Conversation $conversation)
    {
        abort_unless($conversation->user_id === auth()->id(), 403);

        // On reconstitue l'historique complet pour donner le contexte au modèle
        $history = $conversation->messages()
            ->orderBy('created_at')
            ->get(['role', 'content'])
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
            ->toArray();

        return response()->stream(function () use ($history, $conversation) {
            $fullResponse = '';

            // On streame ET on capture chaque morceau
            $this->streamService->streamToOutputAndCapture(
                messages: $history,
                model: $conversation->model,
                onChunk: function (string $chunk) use (&$fullResponse) {
                    $fullResponse .= $chunk;
                },
            );

            // Le stream est fini → on sauvegarde la réponse complète en base
            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $fullResponse,
            ]);

            // Génération du titre au premier échange
            if ($conversation->messages()->count() === 2) {
                $conversation->update([
                    'title' => $this->generateTitle($conversation->messages()->first()->content),
                ]);
            }
        }, headers: [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'no-cache, no-store',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}

