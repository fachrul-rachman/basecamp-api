<?php

namespace App\Providers;

use App\Models\Role;
use App\Services\Ai\EvidenceAssessmentClient;
use App\Services\Ai\OpenAiEvidenceAssessmentClient;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            EvidenceAssessmentClient::class,
            OpenAiEvidenceAssessmentClient::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Gates Scramble's /docs/api (config/scramble.php) outside local
        // environments. Docs are viewed in a plain browser tab, so this
        // accepts either the standard Authorization: Bearer header (e.g.
        // Postman/Insomnia) or a "?token=" query parameter (so a SuperAdmin
        // can open a plain link), applying the same validity rules Sanctum's
        // own guard uses (Laravel\Sanctum\Guard::supportsTokens) since a
        // query-string token isn't something Sanctum itself resolves.
        Gate::define('viewApiDocs', function ($user = null) {
            $subject = auth('sanctum')->user() ?? $this->resolveTokenFromQuery();

            return (bool) $subject?->hasRole(Role::SUPERADMIN);
        });
    }

    private function resolveTokenFromQuery(): mixed
    {
        $token = request()->query('token');

        if (! $token) {
            return null;
        }

        $accessToken = PersonalAccessToken::findToken($token);
        $expiration = config('sanctum.expiration');

        $isValid = $accessToken
            && (! $expiration || $accessToken->created_at->gt(now()->subMinutes($expiration)))
            && (! $accessToken->expires_at || ! $accessToken->expires_at->isPast());

        return $isValid ? $accessToken->tokenable : null;
    }
}
