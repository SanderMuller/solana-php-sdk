<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\DataObjects;

/**
 * [▼ // app/Livewire/SolanaTest.php:71
 * "blockTime" => 1745595893
 * "meta" => array:12 [▼
 * "computeUnitsConsumed" => 450
 * "err" => null
 * "fee" => 80000
 * "innerInstructions" => []
 * "loadedAddresses" => array:2 [▼
 * "readonly" => []
 * "writable" => []
 * ]
 * "logMessages" => array:6 [▶]
 * "postBalances" => array:4 [▼
 * 0 => 1521204000
 * 1 => 500250000
 * 2 => 1
 * 3 => 1
 * ]
 * "postTokenBalances" => []
 * "preBalances" => array:4 [▼
 * 0 => 1521534000
 * 1 => 500000000
 * 2 => 1
 * 3 => 1
 * ]
 * "preTokenBalances" => []
 * "rewards" => []
 * "status" => array:1 [▼
 * "Ok" => null
 * ]
 * ]
 * "slot" => 376663385
 * "transaction" => array:2 [▼
 * "message" => array:4 [▼
 * "accountKeys" => array:4 [▼
 * 0 => "vY1zCUfDwSH2ueZzHVbpj5xEQnyaDgWCJNxuMBYpJfD"
 * 1 => "CJKd7sSPSX59FKKWYZhHEcLhnPcGA8fbYNEXarUV2Fie"
 * 2 => "11111111111111111111111111111111"
 * 3 => "ComputeBudget111111111111111111111111111111"
 * ]
 * "header" => array:3 [▶]
 * "instructions" => array:3 [▼
 * 0 => array:4 [▼
 * "accounts" => []
 * "data" => "3qi1MbrtkR83"
 * "programIdIndex" => 3
 * "stackHeight" => null
 * ]
 * 1 => array:4 [▼
 * "accounts" => []
 * "data" => "Fj2Eoy"
 * "programIdIndex" => 3
 * "stackHeight" => null
 * ]
 * 2 => array:4 [▼
 * "accounts" => array:2 [▼
 * 0 => 0
 * 1 => 1
 * ]
 * "data" => "3Bxs4R5XJvUpL3rP"
 * "programIdIndex" => 2
 * "stackHeight" => null
 * ]
 * ]
 * "recentBlockhash" => "6a6ubBx8Sjvs7WpSchmwXEcVGwo2aqTxTbeFjKs3JArR"
 * ]
 * "signatures" => array:1 [▼
 * 0 => "43CSBxsCZ8mrLouDosze271UFng1om6MDhBnQjsHUKPi81TKXgZnyDh9EjhLFKyrz4x8NmPpAPZKzqvWsARiagez"
 * ]
 * ]
 * ]
 */
final readonly class TransactionStatement
{
    public function __construct(
        public int $blockTime,
        public int $slot,
        public string $signature,
        public Lamports $fee,
        /** @var list<SolTransfer> */
        public array $transfers,
    ) {
        //
    }

    public static function fromResponse(array $response): self
    {
        return new self(
            blockTime: $response['blockTime'],
            slot: $response['slot'],
            signature: $response['transaction']['signatures'][0],
            fee: new Lamports($response['meta']['fee']),
            transfers: SolTransfer::fromTransactionStatement($response),
        );
    }
}
