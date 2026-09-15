<?php

namespace App\Providers;

use App\Events\Security\UntrustedProxyHeadersDetected;
use App\Listeners\Security\LogUntrustedProxyHeaders;
use App\Services\Ai\Contracts\LlmClientInterface;
use App\Services\Ai\Providers\DeepSeekLlmClient;
use App\Services\Ai\Providers\GeminiLlmClient;
use App\Services\Ai\Providers\MockLlmClient;
use App\Services\Ai\Providers\OpenAiLlmClient;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Mailer\Transport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LlmClientInterface::class, function (): LlmClientInterface {
            if (app()->environment('testing')) {
                return new MockLlmClient;
            }

            $provider = strtolower((string) config('services.ai_provider', 'gemini'));

            return match ($provider) {
                'openai' => new OpenAiLlmClient(
                    apiKey: $this->requiredAiKey('openai'),
                    model: (string) config('services.openai.model', 'gpt-4o-mini'),
                    baseUrl: (string) config('services.openai.base_url', 'https://api.openai.com/v1')
                ),
                'deepseek' => new DeepSeekLlmClient(
                    apiKey: $this->requiredAiKey('deepseek'),
                    model: (string) config('services.deepseek.model', 'deepseek-flash'),
                    baseUrl: (string) config('services.deepseek.base_url', 'https://api.deepseek.com')
                ),
                'gemini' => new GeminiLlmClient(
                    apiKey: $this->requiredAiKey('gemini'),
                    model: (string) config('services.gemini.model', 'gemini-2.5-flash')
                ),
                'mock' => app()->environment('local')
                    ? new MockLlmClient
                    : throw new RuntimeException('AI_PROVIDER=mock hanya diizinkan pada environment local atau testing.'),
                default => throw new InvalidArgumentException("AI provider '{$provider}' tidak didukung."),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerWriteRateLimiters();

        Event::listen(UntrustedProxyHeadersDetected::class, LogUntrustedProxyHeaders::class);

        RedirectIfAuthenticated::redirectUsing(function (Request $request): string {
            $user = $request->user();

            if ($user) {
                if (! $user->isActiveAccount()) {
                    return route('account.banned');
                }

                return route($user->defaultDashboardRouteName());
            }

            return route('workspace.home');
        });

        if (! app()->runningInConsole()) {
            $requestHost = request()->getHost();
            $isLocalHost = in_array($requestHost, ['localhost', '127.0.0.1', '::1'], true);
            $hotFile = public_path('hot');

            if (is_file($hotFile)) {
                $hotUrl = trim((string) file_get_contents($hotFile));
                $hotHost = parse_url($hotUrl, PHP_URL_HOST);

                if (! $isLocalHost && $hotHost && $hotHost !== $requestHost) {
                    Vite::useHotFile(storage_path('app/vite.hot'));
                }
            } elseif (! app()->environment('local')) {
                Vite::useHotFile(storage_path('app/vite.hot'));
            }

            if (! app()->environment('local')) {
                URL::forceScheme('https');
            }
        }

        Mail::extend('mailtrap', function (array $config = []) {
            $dsn = $config['dsn'] ?? null;

            if (! $dsn) {
                $token = $config['token'] ?? null;

                if (! $token) {
                    throw new InvalidArgumentException('MAILTRAP_DSN or MAILTRAP_API_TOKEN must be set for the mailtrap mailer.');
                }

                $dsn = 'mailtrap+api://'.rawurlencode($token).'@default';
            }

            return Transport::fromDsn($dsn);
        });

        View::composer('*', function ($view) {
            $guestMode = request()->routeIs('guest.*');
            $view->with('guestMode', $guestMode);
        });
    }

    private function registerWriteRateLimiters(): void
    {
        RateLimiter::for('workspace-write', function (Request $request) {
            return Limit::perMinute(30)->by($this->writeLimiterKey($request, 'workspace-write'));
        });

        RateLimiter::for('api-write', function (Request $request) {
            return Limit::perMinute(30)->by($this->writeLimiterKey($request, 'api-write'));
        });

        RateLimiter::for('bulk-write', function (Request $request) {
            return Limit::perMinute(5)->by($this->writeLimiterKey($request, 'bulk-write'));
        });

        RateLimiter::for('ai-chat', function (Request $request) {
            return Limit::perMinute(max(1, (int) config('services.ai_rate_limit_per_minute', 8)))
                ->by($this->writeLimiterKey($request, 'ai-chat'));
        });
    }

    private function writeLimiterKey(Request $request, string $prefix): string
    {
        $userId = $request->user()?->getAuthIdentifier();
        $identifier = $userId !== null ? 'user:'.$userId : 'ip:'.$request->ip();

        return $prefix.':'.$identifier;
    }

    private function requiredAiKey(string $provider): string
    {
        $key = (string) config("services.{$provider}.api_key", '');

        if ($key === '') {
            throw new RuntimeException(strtoupper($provider).'_API_KEY tidak dikonfigurasi.');
        }

        return $key;
    }
}
