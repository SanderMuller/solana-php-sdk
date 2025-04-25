<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\DataObjects;

final readonly class SolTransfer
{
    public function __construct(
        public string   $from,
        public string   $to,
        public Lamports $amount,
    ) {
        //
    }

    /**
     * @return list<self>
     */
    public static function fromTransactionStatement(array $response): array
    {
        $accountKeys = $response['transaction']['message']['accountKeys'] ?? [];
        $pre = $response['meta']['preBalances'] ?? [];
        $post = $response['meta']['postBalances'] ?? [];

        $changes = [];

        foreach ($accountKeys as $i => $pubkey) {
            $delta = $post[$i] - $pre[$i];
            if ($delta !== 0) {
                $changes[] = [
                    'address' => $pubkey,
                    'delta' => $delta,
                ];
            }
        }

        // Partition into gains and losses
        $senders = array_filter($changes, static fn (array $c): bool => $c['delta'] < 0);
        $recipients = array_filter($changes, static fn (array $c): bool => $c['delta'] > 0);

        // If there's only one sender and one recipient, we can map it directly
        if (count($senders) === 1 && count($recipients) === 1) {
            $from = reset($senders);
            $to = reset($recipients);

            return [
                new self(
                    from: $from['address'],
                    to: $to['address'],
                    amount: new Lamports($to['delta'])
                ),
            ];
        }

        // If multiple, attempt to pair up (naive greedy pairing)
        $transfers = [];
        foreach ($senders as $sender) {
            $remaining = abs($sender['delta']);
            foreach ($recipients as $i => $recipient) {
                if ($recipient['delta'] === 0) {
                    continue;
                }

                $transferAmount = min($remaining, $recipient['delta']);
                if ($transferAmount <= 0) {
                    continue;
                }

                $transfers[] = new self(
                    from: $sender['address'],
                    to: $recipient['address'],
                    amount: new Lamports($transferAmount)
                );

                // Adjust deltas
                $remaining -= $transferAmount;
                $recipients[$i]['delta'] -= $transferAmount;

                if ($remaining <= 0) {
                    break;
                }
            }
        }

        return $transfers;
    }
}
