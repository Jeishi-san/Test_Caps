<?php

namespace App\Providers;

use App\Models\Document;
use App\Models\Folder;
use App\Services\DocumentAccessService;
use App\Services\DocumentEncryptionService;
use App\Services\DocumentFileService;
use App\Services\DocumentNotificationService;
use App\Services\DocumentService;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(DocumentEncryptionService::class, function ($app) {
            return new DocumentEncryptionService($app->make(\App\Services\Stego\CryptoService::class));
        });

        $this->app->singleton(DocumentFileService::class, function ($app) {
            return new DocumentFileService($app->make(DocumentEncryptionService::class));
        });

        $this->app->singleton(DocumentAccessService::class, function () {
            return new DocumentAccessService();
        });

        $this->app->singleton(DocumentNotificationService::class, function () {
            return new DocumentNotificationService();
        });

        $this->app->singleton(DocumentService::class, function ($app) {
            return new DocumentService(
                $app->make(\App\Services\Stego\CryptoService::class),
                $app->make(DocumentEncryptionService::class),
                $app->make(DocumentFileService::class),
                $app->make(DocumentAccessService::class),
                $app->make(DocumentNotificationService::class),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Register short-key morphMap so polymorphic type columns store
        // stable short strings ('folder', 'document') instead of full
        // class paths that break silently on rename/move.
        Relation::morphMap([
            'folder'   => Folder::class,
            'document' => Document::class,
        ]);
    }
}
