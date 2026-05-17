<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tx\Decoded;

/**
 * Parses the `meta.logMessages` array returned by `getTransaction`
 * into a flat list of {@see DecodedLogEvent}.
 *
 * The decoder is regex-based and **fail-open**: lines it does not
 * recognise become `KIND_UNKNOWN` events with the original line in
 * `message`, never an exception. A truncation marker (`Log truncated`)
 * is emitted as a synthetic `KIND_TRUNCATED` event so consumers see
 * the boundary without having to sniff for it.
 *
 * Note: `programId` on `log` / `data` events tracks the most recent
 * `invoke` frame on a depth-aware stack — that matches what
 * Solana's own log explorer shows. If the input is malformed (a
 * `Program log:` line before any `invoke`), `programId` stays null.
 *
 * @api
 */
final class LogDecoder
{
    /**
     * @param list<string> $logs
     * @return list<DecodedLogEvent>
     */
    public static function decode(array $logs): array
    {
        $out = [];

        /** @var list<string> $stack stack of program ids active at each depth */
        $stack = [];

        foreach ($logs as $line) {
            if (! is_string($line)) {
                continue;
            }

            $event = self::parseLine($line, $stack);
            if ($event !== null) {
                $out[] = $event;
            }
        }

        return $out;
    }

    /**
     * @param list<string> $stack
     */
    private static function parseLine(string $line, array &$stack): ?DecodedLogEvent
    {
        if (preg_match('/^Program (\S+) invoke \[(\d+)\]$/', $line, $m) === 1) {
            $stack[] = $m[1];

            return new DecodedLogEvent(
                kind: DecodedLogEvent::KIND_INVOKE,
                programId: $m[1],
                depth: (int) $m[2],
            );
        }

        if (preg_match('/^Program (\S+) success$/', $line, $m) === 1) {
            array_pop($stack);

            return new DecodedLogEvent(
                kind: DecodedLogEvent::KIND_SUCCESS,
                programId: $m[1],
            );
        }

        if (preg_match('/^Program (\S+) failed: (.+)$/', $line, $m) === 1) {
            array_pop($stack);

            return new DecodedLogEvent(
                kind: DecodedLogEvent::KIND_FAILURE,
                programId: $m[1],
                error: $m[2],
            );
        }

        if (preg_match('/^Program (\S+) consumed (\d+) of (\d+) compute units$/', $line, $m) === 1) {
            return new DecodedLogEvent(
                kind: DecodedLogEvent::KIND_CONSUMED,
                programId: $m[1],
                consumed: (int) $m[2],
                budget: (int) $m[3],
            );
        }

        if (preg_match('/^Program return: (\S+) (.*)$/', $line, $m) === 1) {
            $bytes = base64_decode($m[2], true);

            return new DecodedLogEvent(
                kind: DecodedLogEvent::KIND_RETURN_DATA,
                programId: $m[1],
                bytes: $bytes === false ? null : $bytes,
            );
        }

        if (preg_match('/^Program data: (.*)$/', $line, $m) === 1) {
            $bytes = base64_decode($m[1], true);

            return new DecodedLogEvent(
                kind: DecodedLogEvent::KIND_DATA,
                programId: self::currentFrame($stack),
                bytes: $bytes === false ? null : $bytes,
            );
        }

        if (str_starts_with($line, 'Program log: ')) {
            return new DecodedLogEvent(
                kind: DecodedLogEvent::KIND_LOG,
                programId: self::currentFrame($stack),
                message: substr($line, strlen('Program log: ')),
            );
        }

        if (str_starts_with($line, 'Log truncated')) {
            return new DecodedLogEvent(
                kind: DecodedLogEvent::KIND_TRUNCATED,
                message: $line,
            );
        }

        return new DecodedLogEvent(
            kind: DecodedLogEvent::KIND_UNKNOWN,
            message: $line,
        );
    }

    /**
     * @param list<string> $stack
     */
    private static function currentFrame(array $stack): ?string
    {
        return $stack === [] ? null : $stack[count($stack) - 1];
    }
}
