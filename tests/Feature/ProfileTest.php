<?php

use App\Models\User;
use App\Models\UserProfileAvatar;
use Illuminate\Http\UploadedFile;
use Livewire\Volt\Volt;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->get('/profile');

    $response
        ->assertOk()
        ->assertSeeVolt('profile.update-profile-details-form')
        ->assertSeeVolt('profile.update-password-form')
        ->assertSeeVolt('profile.delete-user-form');
});

test('profile details can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $component = Volt::test('profile.update-profile-details-form')
        ->set('display_name', 'Nama Workspace')
        ->set('bio', 'Mahasiswa yang suka merapikan jadwal.')
        ->set('location', 'Jakarta')
        ->set('theme_preference', 'dark')
        ->set('timezone', 'Asia/Jakarta')
        ->set('locale', 'id')
        ->call('updateProfileDetails');

    $component
        ->assertHasNoErrors()
        ->assertDispatched('profile-details-updated')
        ->assertDispatched('profile-theme-updated');

    $this->assertDatabaseHas('user_profiles', [
        'user_id' => $user->id,
        'display_name' => 'Nama Workspace',
        'location' => 'Jakarta',
        'theme_preference' => 'dark',
        'timezone' => 'Asia/Jakarta',
        'locale' => 'id',
    ]);
});

test('theme preference can be updated from the sidebar toggle endpoint', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $this->patchJson(route('profile.theme.update'), [
        'theme_preference' => 'dark',
    ])
        ->assertOk()
        ->assertJson([
            'theme_preference' => 'dark',
        ]);

    $this->assertDatabaseHas('user_profiles', [
        'user_id' => $user->id,
        'theme_preference' => 'dark',
    ]);
});

test('profile avatar is resized compressed and stored in the database', function () {
    if (! function_exists('imagecreatetruecolor') || ! function_exists('imagewebp')) {
        $this->markTestSkipped('GD with WebP support is required to test avatar optimization.');
    }

    $user = User::factory()->create();

    $this->actingAs($user);

    $image = imagecreatetruecolor(256, 256);
    $path = tempnam(sys_get_temp_dir(), 'avatar_');
    imagewebp($image, $path, 75);
    imagedestroy($image);

    $component = Volt::test('profile.update-profile-details-form')
        ->set('avatar', UploadedFile::fake()->createWithContent('avatar.webp', file_get_contents($path)))
        ->call('updateProfileDetails');

    @unlink($path);

    $component
        ->assertHasNoErrors()
        ->assertDispatched('profile-details-updated');

    $avatar = UserProfileAvatar::query()->where('user_id', $user->id)->first();

    expect($avatar)->not->toBeNull()
        ->and($avatar->mime_type)->toBe('image/webp')
        ->and($avatar->width)->toBe(256)
        ->and($avatar->height)->toBe(256)
        ->and($avatar->size)->toBeGreaterThan(0)
        ->and(substr($avatar->image, 0, 4))->toBe('RIFF')
        ->and(substr($avatar->image, 8, 4))->toBe('WEBP');

    $this->get(route('profile.avatar.show', $user))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/webp');
});

test('profile avatar can be removed from the database', function () {
    $user = User::factory()->create();
    $user->profileAvatar()->create([
        'image' => 'avatar-binary',
        'mime_type' => 'image/webp',
        'width' => 64,
        'height' => 64,
        'size' => 13,
    ]);

    $this->actingAs($user);

    $component = Volt::test('profile.update-profile-details-form')
        ->call('removeAvatar');

    $component
        ->assertHasNoErrors()
        ->assertDispatched('profile-details-updated')
        ->assertSet('remove_avatar', true);

    $this->assertDatabaseHas('user_profile_avatars', [
        'user_id' => $user->id,
    ]);

    $component->call('updateProfileDetails')
        ->assertHasNoErrors()
        ->assertDispatched('profile-details-updated');

    $this->assertDatabaseMissing('user_profile_avatars', [
        'user_id' => $user->id,
    ]);
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $component = Volt::test('profile.update-profile-information-form')
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->call('updateProfileInformation');

    $component
        ->assertHasNoErrors()
        ->assertNoRedirect();

    $user->refresh();

    $this->assertSame('Test User', $user->name);
    $this->assertSame('test@example.com', $user->email);
    $this->assertNull($user->email_verified_at);
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $component = Volt::test('profile.update-profile-information-form')
        ->set('name', 'Test User')
        ->set('email', $user->email)
        ->call('updateProfileInformation');

    $component
        ->assertHasNoErrors()
        ->assertNoRedirect();

    $this->assertNotNull($user->refresh()->email_verified_at);
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $component = Volt::test('profile.delete-user-form')
        ->set('password', 'password')
        ->call('deleteUser');

    $component
        ->assertHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
    $this->assertNull($user->fresh());
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $component = Volt::test('profile.delete-user-form')
        ->set('password', 'wrong-password')
        ->call('deleteUser');

    $component
        ->assertHasErrors('password')
        ->assertNoRedirect();

    $this->assertNotNull($user->fresh());
});
