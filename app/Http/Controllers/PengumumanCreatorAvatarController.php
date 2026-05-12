<?php

namespace App\Http\Controllers;

use App\Models\AffiliationBroadcast;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PengumumanCreatorAvatarController extends Controller
{
    public function __invoke(Request $request, User $user): Response
    {
        $viewer = $request->user();

        $canViewCreator = $viewer && (
            (int) $viewer->id === (int) $user->id
            || AffiliationBroadcast::query()
                ->where('created_by', $user->id)
                ->visibleToUser($viewer)
                ->exists()
        );

        abort_unless($canViewCreator, 404);

        $avatar = $user->profileAvatar;
        abort_if(! $avatar, 404);

        return response($avatar->image)
            ->header('Content-Type', $avatar->mime_type)
            ->header('Content-Length', (string) $avatar->size)
            ->header('Cache-Control', 'private, max-age=3600');
    }
}
