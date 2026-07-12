<?php

namespace App\Providers;

use App\Domain\Catalog\Repositories\CategoryRepositoryInterface;
use App\Domain\Catalog\Repositories\ProductRepositoryInterface;
use App\Domain\Payment\Repositories\PaymentRepositoryInterface;
use App\Domain\Payment\Services\PaymentGatewayResolverInterface;
use App\Domain\Order\Repositories\OrderRepositoryInterface;
use App\Domain\Order\Services\OrderNumberGeneratorInterface;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Domain\Storage\Repositories\UploadRepositoryInterface;
use App\Domain\Storage\Services\PublicUrlGeneratorInterface;
use App\Domain\Storage\Services\StorageDriverResolverInterface;
use App\Domain\SystemConfig\Repositories\SystemConfigRepositoryInterface;
use App\Domain\SystemConfig\Services\ConfigEncryptionInterface;
use App\Infrastructure\Encryption\LaravelConfigEncryption;
use App\Infrastructure\Order\DatabaseOrderNumberGenerator;
use App\Infrastructure\Payment\ConfigPaymentGatewayResolver;
use App\Infrastructure\Persistence\Eloquent\EloquentCategoryRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentOrderRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentPaymentRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentProductRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentSystemConfigRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentUploadRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentUserRepository;
use App\Infrastructure\Storage\ConfigPublicUrlGenerator;
use App\Infrastructure\Storage\Drivers\LocalStorageDriver;
use App\Infrastructure\Storage\Drivers\OssStorageDriver;
use App\Infrastructure\Storage\Resolvers\HardcodedStorageDriverResolver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ConfigEncryptionInterface::class, LaravelConfigEncryption::class);
        $this->app->bind(SystemConfigRepositoryInterface::class, EloquentSystemConfigRepository::class);
        $this->app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);
        $this->app->bind(CategoryRepositoryInterface::class, EloquentCategoryRepository::class);
        $this->app->bind(ProductRepositoryInterface::class, EloquentProductRepository::class);
        $this->app->bind(OrderRepositoryInterface::class, EloquentOrderRepository::class);
        $this->app->bind(PaymentRepositoryInterface::class, EloquentPaymentRepository::class);
        $this->app->bind(PaymentGatewayResolverInterface::class, ConfigPaymentGatewayResolver::class);
        $this->app->bind(OrderNumberGeneratorInterface::class, DatabaseOrderNumberGenerator::class);

        $this->app->singleton(LocalStorageDriver::class);
        $this->app->singleton(OssStorageDriver::class);
        $this->app->bind(PublicUrlGeneratorInterface::class, ConfigPublicUrlGenerator::class);
        $this->app->bind(UploadRepositoryInterface::class, EloquentUploadRepository::class);
        $this->app->bind(StorageDriverResolverInterface::class, HardcodedStorageDriverResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
