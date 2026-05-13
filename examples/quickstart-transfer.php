<?php declare(strict_types=1);

/**
 * Quickstart: generate a wallet, airdrop devnet SOL, transfer half of it.
 *
 * Run:
 *   composer install   # once
 *   php examples/quickstart-transfer.php
 *
 * Output:
 *   payer:       <base58 pubkey>
 *   airdrop sig: <signature>
 *   balance:     1.000000000 SOL
 *   transfer:    https://explorer.solana.com/tx/<sig>?cluster=devnet
 *
 * The devnet faucet is rate-limited. If the airdrop step fails, wait ~30 s
 * and retry, or top up the printed pubkey via https://faucet.solana.com.
 */

require __DIR__ . '/../vendor/autoload.php';

use SanderMuller\SolanaPhpSdk\Bootstrap;
use SanderMuller\SolanaPhpSdk\Connection;
use SanderMuller\SolanaPhpSdk\Enum\Network;
use SanderMuller\SolanaPhpSdk\Keypair;
use SanderMuller\SolanaPhpSdk\Programs\SystemProgram;
use SanderMuller\SolanaPhpSdk\Transaction;

// Tiny inline config so the script is fully self-contained.
$configPath = sys_get_temp_dir() . '/solana-php-sdk-example.php';
file_put_contents($configPath, '<?php return ' . var_export([
    'token_program_id' => 'TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA',
    'network'          => Network::DEVNET,
], true) . ';');

$container  = Bootstrap::createContainer($configPath);
$connection = $container->get(Connection::class);

// 1. Wallet.
$payer = Keypair::generate();
echo "payer:        ", $payer->getPublicKey()->toBase58(), PHP_EOL;

// 2. Airdrop 1 SOL.
$airdropSig = $connection->requestAirdrop([
    $payer->getPublicKey()->toBase58(),
    1_000_000_000,
]);
echo "airdrop sig:  {$airdropSig}", PHP_EOL;
$connection->confirmTransaction($airdropSig);

// 3. Balance.
$lamports = $connection->getBalance($payer->getPublicKey()->toBase58());
echo "balance:      ", number_format($lamports / 1_000_000_000, 9), " SOL", PHP_EOL;

// 4. Transfer half of it to a fresh address.
$recipient = Keypair::generate()->getPublicKey();
$tx = new Transaction(feePayer: $payer->getPublicKey());
$tx->addInstructions(SystemProgram::transfer(
    fromPubkey:  $payer->getPublicKey(),
    toPublicKey: $recipient,
    lamports:    500_000_000,
));

$signature = $connection->sendTransaction($tx, signers: [$payer]);
$connection->confirmTransaction((string) $signature);

echo "transfer:     https://explorer.solana.com/tx/{$signature}?cluster=devnet", PHP_EOL;
