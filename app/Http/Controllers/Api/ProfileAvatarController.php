<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProfileAvatarController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $avatar = $request->user()?->profileAvatar;
        abort_if(! $avatar, 404);

        return response($avatar->image)
            ->header('Content-Type', $avatar->mime_type)
            ->header('Content-Length', (string) $avatar->size)
            ->header('Cache-Control', 'private, max-age=3600');
    }
}
