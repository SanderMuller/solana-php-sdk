<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk;

use Illuminate\Container\Container;
use SanderMuller\SolanaPhpSdk\Enum\Network;
use SanderMuller\SolanaPhpSdk\Programs\SnsProgram;
use SanderMuller\SolanaPhpSdk\Rpc\TransportFactory;
use SanderMuller\SolanaPhpSdk\Services\SolanaRpcClient;

final class Bootstrap
{
    public static function createContainer(string $configPath): Container
    {
        // Load configuration
        Config::load($configPath);

        $container = Container::getInstance();
        $container->singleton(SolanaRpcClient::class, static function (): SolanaRpcClient {
            $network = Config::get('network');
            $transportConfig = Config::get('transport');
            /**
             * @var array{
             *     mode?: string,
             *     urls?: list<string>,
             *     headers?: array<string, string>,
             *     timeout?: float|int,
             *     retry?: array{max_attempts?: int, base_delay_ms?: int, max_delay_ms?: int}|null,
             * }|null $transportConfig
             */
            $transport = is_array($transportConfig)
                ? TransportFactory::fromConfig($transportConfig)
                : null;

            return new SolanaRpcClient(
                $network instanceof Network ? $network : Network::MAINNET,
                $transport,
            );
        });
        $container->bind(SnsProgram::class, static fn (): SnsProgram => new SnsProgram());

        return $container;
    }
}
