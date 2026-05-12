<?php declare(strict_types=1);

/**
 * Load an Anchor IDL, build one of its instructions, simulate it.
 *
 * Run:
 *   php examples/anchor-call.php
 *
 * Uses the test-fixture IDL shipped with the package as a stand-in for any
 * real Anchor program JSON you might drop in.
 */

require __DIR__ . '/../vendor/autoload.php';

use Collectiq\SolanaPhpSdk\Anchor\AnchorIdl;
use Collectiq\SolanaPhpSdk\Bootstrap;
use Collectiq\SolanaPhpSdk\Connection;
use Collectiq\SolanaPhpSdk\Enum\Network;
use Collectiq\SolanaPhpSdk\Keypair;
use Collectiq\SolanaPhpSdk\Transaction;

$configPath = sys_get_temp_dir() . '/solana-php-sdk-example.php';
file_put_contents($configPath, '<?php return ' . var_export([
    'token_program_id' => 'TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA',
    'network'          => Network::DEVNET,
], true) . ';');

$container  = Bootstrap::createContainer($configPath);
$connection = $container->get(Connection::class);

// Point at any Anchor IDL file you've exported via `anchor idl fetch` or
// shipped in your repo. We're reusing the package's test fixture so the
// example is runnable as-is.
$idlPath = __DIR__ . '/../tests/Fixtures/anchor_idl_minimal.json';
$idl     = AnchorIdl::fromFile($idlPath);

echo "program:      {$idl->programId}", PHP_EOL;
echo "instructions: ", implode(', ', $idl->instructionNames()), PHP_EOL;

$payer    = Keypair::generate();
$counter  = Keypair::generate()->getPublicKey();
$cosigner = Keypair::generate()->getPublicKey();

$ix = $idl->instruction('increment')->build(
    accounts: [
        'counter' => $counter,
        'user'    => $payer->getPublicKey(),
    ],
    args: ['amount' => 7],
);

$tx = (new Transaction(feePayer: $payer->getPublicKey()))->addInstructions($ix);

echo "ix data:      0x", bin2hex($ix->data->toString()), PHP_EOL;
echo "(no live RPC call — this fixture program isn't deployed)", PHP_EOL;
