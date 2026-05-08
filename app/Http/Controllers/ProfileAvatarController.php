<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Response;

class ProfileAvatarController extends Controller
{
    public function __invoke(User $user): Response
    {
        abort_unless(auth()->id() === $user->id, 403);

        $avatar = $user->profileAvatar;
        abort_if(! $avatar, 404);

        return response($avatar->image)
            ->header('Content-Type', $avatar->mime_type)
            ->header('Content-Length', (string) $avatar->size)
            ->header('Cache-Control', 'private, max-age=3600');
    }
}
