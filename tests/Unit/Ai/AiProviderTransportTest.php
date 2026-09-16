<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\Exceptions\AiProviderException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use Illuminate\Http\Client\ConnectionException;
use Tests\TestCase;

class AiProviderTransportTest extends TestCase
{
    public function test_only_known_transport_timeout_is_classified_as_timeout(): void
    {
        foreach ([28 => 'provider_timeout', 6 => 'provider_connection_failed', 35 => 'provider_connection_failed', 56 => 'provider_connection_failed'] as $errno => $code) {
            $cause = new ConnectException('private transport details', new Request('POST', 'http://diagnostic.invalid'), null, ['errno' => $errno]);
            $error = AiProviderException::transportFailure(new ConnectionException('private transport details', 0, $cause));
            $this->assertSame($code, $error->errorCode);
            $this->assertStringNotContainsString('private transport', $error->getMessage());
        }
        $this->assertSame('provider_connection_failed', AiProviderException::transportFailure(new ConnectionException('unknown'))->errorCode);
    }
}
