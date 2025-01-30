<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk;

final class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/solana-php-sdk.php' => config_path('solana-php-sdk.php'),
        ]);

        Bootstrap::createContainer(
            file_exists(config_path('solana-php-sdk.php'))
                ? config_path('solana-php-sdk.php')
                : __DIR__ . '/../config/solana-php-sdk.php'
        );
    }
}
