<?php

use App\Http\Controllers\Api\AuthController;
use App\Models\AffiliationRequest;
use App\Models\User;
use App\Models\UserProfileAvatar;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\UploadedFile;

test('mobile user can update profile details', function () {
    $user = User::factory()->create();
    $token = mobileApiToken($user);

    $this->withToken($token)->patchJson(route('api.v1.profile.update'), [
        'display_name' => 'Nama Mobile',
        'bio' => 'Bio dari Flutter',
        'phone' => '08123456789',
        'date_of_birth' => '2001-05-10',
        'gender' => 'prefer_not_to_say',
        'location' => 'Jakarta',
        'theme_preference' => 'dark',
        'timezone' => 'Asia/Jakarta',
        'locale' => 'id',
    ])
        ->assertOk()
        ->assertJsonPath('data.user.profile.display_name', 'Nama Mobile')
        ->assertJsonPath('data.user.profile.theme_preference', 'dark');

    $this->assertDatabaseHas('user_profiles', [
        'user_id' => $user->id,
        'display_name' => 'Nama Mobile',
        'bio' => 'Bio dari Flutter',
        'phone' => '08123456789',
        'location' => 'Jakarta',
        'theme_preference' => 'dark',
        'timezone' => 'Asia/Jakarta',
        'locale' => 'id',
    ]);
});

test('mobile user can upload and fetch profile avatar without loading image in auth me payload', function () {
    if (! function_exists('imagecreatetruecolor') || ! function_exists('imagewebp')) {
        $this->markTestSkipped('GD with WebP support is required to test avatar upload.');
    }

    $user = User::factory()->create();
    $token = mobileApiToken($user);

    $image = imagecreatetruecolor(128, 128);
    $path = tempnam(sys_get_temp_dir(), 'api_avatar_');
    imagewebp($image, $path, 75);
    imagedestroy($image);

    $this->withToken($token)->post(route('api.v1.profile.avatar.store'), [
        'avatar' => UploadedFile::fake()->createWithContent('avatar.webp', file_get_contents($path)),
    ])
        ->assertOk()
        ->assertJsonPath('data.user.profile.has_avatar', true)
        ->assertJsonPath('data.user.profile.avatar_url', '/api/v1/profile/avatar');

    @unlink($path);

    $avatar = UserProfileAvatar::query()->where('user_id', $user->id)->first();

    expect($avatar)->not->toBeNull()
        ->and($avatar->mime_type)->toBe('image/webp')
        ->and($avatar->width)->toBe(128)
        ->and($avatar->height)->toBe(128)
        ->and($avatar->size)->toBeGreaterThan(0);

    $this->withToken($token)->get(route('api.v1.profile.avatar'))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/webp');

    $this->withToken($token)->getJson(route('api.v1.auth.me'))
        ->assertOk()
        ->assertJsonPath('data.profile.has_avatar', true)
        ->assertJsonPath('data.profile.avatar_url', '/api/v1/profile/avatar');
});

test('mobile user can delete profile avatar', function () {
    $user = User::factory()->create();
    $user->profileAvatar()->create([
        'image' => 'avatar-binary',
        'mime_type' => 'image/webp',
        'width' => 64,
        'height' => 64,
        'size' => 13,
    ]);
    $token = mobileApiToken($user);

    $this->withToken($token)->deleteJson(route('api.v1.profile.avatar.destroy'))
        ->assertOk()
        ->assertJsonPath('data.user.profile.has_avatar', false);

    $this->assertDatabaseMissing('user_profile_avatars', [
        'user_id' => $user->id,
    ]);
});

test('mobile user can submit and cancel affiliation request', function () {
    $user = User::factory()->create([
        'affiliation_status' => 'pending',
    ]);
    $token = mobileApiToken($user);

    $this->withToken($token)->postJson(route('api.v1.profile.affiliation-request.store'), [
        'affiliation_type' => 'institute',
        'affiliation_name' => ' Institut Teknologi Bandung ',
        'student_id_type' => 'nim',
        'student_id_number' => ' 1234556 ',
    ])
        ->assertCreated()
        ->assertJsonPath('data.user.affiliation.pending_request.type', 'institute')
        ->assertJsonPath('data.user.affiliation.pending_request.name', 'Institut Teknologi Bandung')
        ->assertJsonPath('data.user.affiliation.pending_request.student_id_number', '1234556');

    $this->assertDatabaseHas('affiliation_requests', [
        'user_id' => $user->id,
        'affiliation_type' => 'institute',
        'affiliation_name' => 'Institut Teknologi Bandung',
        'student_id_type' => 'nim',
        'student_id_number' => '1234556',
        'status' => AffiliationRequest::STATUS_PENDING,
    ]);

    $this->withToken($token)->deleteJson(route('api.v1.profile.affiliation-request.destroy'))
        ->assertOk()
        ->assertJsonPath('data.user.affiliation.pending_request', null);

    $this->assertDatabaseHas('affiliation_requests', [
        'user_id' => $user->id,
        'status' => AffiliationRequest::STATUS_CANCELED,
    ]);
});

test('mobile user can update password and revoke api token', function () {
    $user = User::factory()->create([
        'password' => Hash::make('old-password-123'),
    ]);
    $token = mobileApiToken($user);

    $this->withToken($token)->patchJson(route('api.v1.profile.password.update'), [
        'current_password' => 'old-password-123',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertOk();

    expect(Hash::check('new-password-123', $user->fresh()->password))->toBeTrue();

    expect($user->tokens()->count())->toBe(0);
});

test('mobile user can delete own account with password confirmation', function () {
    $user = User::factory()->create([
        'password' => Hash::make('delete-password-123'),
    ]);
    $token = mobileApiToken($user);

    $this->withToken($token)->deleteJson(route('api.v1.profile.account.destroy'), [
        'password' => 'delete-password-123',
    ])->assertOk();

    $this->assertDatabaseMissing('users', [
        'id' => $user->id,
    ]);
});

function mobileApiToken(User $user): string
{
    return $user->createToken(
        'flutter-test',
        [AuthController::MOBILE_API_ABILITY],
        now()->addDay()
    )->plainTextToken;
}
