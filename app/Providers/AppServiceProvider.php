<?php
namespace App\Providers;
use App\Services\Ai\{AiProvider,GeminiProvider};
use Illuminate\Support\ServiceProvider;
class AppServiceProvider extends ServiceProvider {
    public function register(): void { $this->app->bind(AiProvider::class,GeminiProvider::class); }
    public function boot(): void {}
}
