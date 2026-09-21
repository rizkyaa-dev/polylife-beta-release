<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\Science\ScienceCacheScope;
use Tests\TestCase;

class ScienceCacheScopeTest extends TestCase
{
    public function test_scope_is_stable_opaque_and_owner_specific(): void
    {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('k', 32))]);
        $scope = app(ScienceCacheScope::class);
        $first = $scope->forUser(41);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $first);
        $this->assertSame($first, $scope->forUser(41));
        $this->assertNotSame($first, $scope->forUser(42));
        $this->assertNotSame(hash('sha256', '41'), $first);
    }
}
