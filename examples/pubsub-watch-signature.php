<?php declare(strict_types=1);

/**
 * Subscribe to a transaction signature over WebSockets and block until the
 * network reports it landed (or errored).
 *
 * Run:
 *   php examples/pubsub-watch-signature.php <signature>
 *
 * The signature can come from any prior send — e.g. the one printed by
 * examples/quickstart-transfer.php.
 */

require __DIR__ . '/../vendor/autoload.php';

use Collectiq\SolanaPhpSdk\Enum\Network;
use Collectiq\SolanaPhpSdk\Services\SolanaPubSubClient;

$signature = $argv[1] ?? null;
if ($signature === null) {
    fwrite(STDERR, "usage: php examples/pubsub-watch-signature.php <signature>\n");
    exit(1);
}

$pubsub = new SolanaPubSubClient(network: Network::DEVNET);

$subId = $pubsub->signatureSubscribe($signature, ['commitment' => 'finalized']);
echo "subscribed (id={$subId}), waiting for finalisation...", PHP_EOL;

foreach ($pubsub->listen(maxEvents: 1) as $event) {
    $value = $event['result']['value'] ?? null;
    $err = is_array($value) ? ($value['err'] ?? null) : null;

    if ($err === null) {
        echo "✓ finalised cleanly", PHP_EOL;
    } else {
        echo "✗ on-chain error: ", json_encode($err), PHP_EOL;
    }
}

$pubsub->unsubscribe($subId);
$pubsub->close();
