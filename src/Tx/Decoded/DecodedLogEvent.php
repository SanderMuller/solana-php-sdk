<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tx\Decoded;

/**
 * One parsed line from `meta.logMessages`. Kinds:
 *
 *  - `invoke`     — populates `programId`, `depth`.
 *  - `success`    — populates `programId`.
 *  - `failure`    — populates `programId`, `error`.
 *  - `log`        — populates `programId` (when known) and `message`.
 *  - `data`       — populates `programId` (when known) and `bytes` (decoded base64 → binary).
 *  - `consumed`   — populates `programId`, `consumed`, `budget`.
 *  - `returnData` — populates `programId` and `bytes` (decoded base64 → binary).
 *  - `truncated`  — synthetic event emitted when the validator clipped the log buffer.
 *  - `unknown`    — non-matching line preserved verbatim in `message` so callers can grep.
 *
 * One DTO with optional fields keeps consumer code straightforward;
 * a class-per-variant hierarchy would be overkill for the cardinality.
 *
 * @api
 */
final readonly class DecodedLogEvent
{
    public const string KIND_INVOKE = 'invoke';

    public const string KIND_SUCCESS = 'success';

    public const string KIND_FAILURE = 'failure';

    public const string KIND_LOG = 'log';

    public const string KIND_DATA = 'data';

    public const string KIND_CONSUMED = 'consumed';

    public const string KIND_RETURN_DATA = 'returnData';

    public const string KIND_TRUNCATED = 'truncated';

    public const string KIND_UNKNOWN = 'unknown';

    public function __construct(
        public string $kind,
        public ?string $programId = null,
        public ?int $depth = null,
        public ?string $message = null,
        public ?string $bytes = null,
        public ?int $consumed = null,
        public ?int $budget = null,
        public ?string $error = null,
    ) {}
}
