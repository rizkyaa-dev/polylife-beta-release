<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileThemePreferenceController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'theme_preference' => ['required', Rule::in(['system', 'light', 'dark'])],
        ]);

        $user = $request->user();
        $profile = $user->profile()->firstOrNew(['user_id' => $user->id]);

        if ($profile->exists && $profile->theme_preference === $validated['theme_preference']) {
            return response()->json([
                'theme_preference' => $profile->theme_preference,
            ]);
        }

        $profile->theme_preference = $validated['theme_preference'];
        $profile->save();

        return response()->json([
            'theme_preference' => $profile->theme_preference,
        ]);
    }
}
