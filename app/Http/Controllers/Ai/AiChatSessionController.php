<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Models\AiChatSession;
use App\Services\Ai\AiConversationBranchService;
use App\Services\Ai\Exceptions\AiConversationBusyException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AiChatSessionController extends Controller
{
    public function __construct(private readonly AiConversationBranchService $branchService) {}

    public function update(Request $request, int $session): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
        ]);

        $title = trim($validated['title']);
        if ($title === '') {
            throw ValidationException::withMessages([
                'title' => 'Nama percakapan wajib diisi.',
            ]);
        }

        $chatSession = $this->ownedSession($request, $session);
        $chatSession->update(['title' => $title]);

        return response()->json([
            'status' => 'success',
            'session' => [
                'id' => $chatSession->id,
                'title' => $chatSession->title,
            ],
        ]);
    }

    public function destroy(Request $request, int $session): JsonResponse
    {
        $chatSession = $this->ownedSession($request, $session);
        $chatSession->delete();

        return response()->json([
            'status' => 'success',
            'session_id' => $session,
        ]);
    }

    public function activateBranch(Request $request, int $branch): JsonResponse
    {
        try {
            $session = $this->branchService->activate($request->user(), $branch);

            return response()->json([
                'status' => 'success',
                'session_id' => $session->id,
                'branch_id' => $branch,
                'url' => route('ai.workspace', ['session' => $session->id]),
            ]);
        } catch (AiConversationBusyException $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], 409);
        }
    }

    private function ownedSession(Request $request, int $session): AiChatSession
    {
        return $request->user()->aiChatSessions()->findOrFail($session);
    }
}
