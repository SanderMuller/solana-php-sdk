<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Tests\Unit\Tx\Decoded;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SanderMuller\SolanaPhpSdk\Tx\Decoded\DecodedLogEvent;
use SanderMuller\SolanaPhpSdk\Tx\Decoded\LogDecoder;

/** @internal */
final class LogDecoderTest extends TestCase
{
    #[Test]
    public function decodes_invoke_with_depth(): void
    {
        $events = LogDecoder::decode(['Program 11111111111111111111111111111111 invoke [1]']);

        self::assertCount(1, $events);
        self::assertSame(DecodedLogEvent::KIND_INVOKE, $events[0]->kind);
        self::assertSame('11111111111111111111111111111111', $events[0]->programId);
        self::assertSame(1, $events[0]->depth);
    }

    #[Test]
    public function decodes_success(): void
    {
        $events = LogDecoder::decode([
            'Program 11111111111111111111111111111111 invoke [1]',
            'Program 11111111111111111111111111111111 success',
        ]);

        self::assertCount(2, $events);
        self::assertSame(DecodedLogEvent::KIND_SUCCESS, $events[1]->kind);
        self::assertSame('11111111111111111111111111111111', $events[1]->programId);
    }

    #[Test]
    public function decodes_failure_with_error_string(): void
    {
        $events = LogDecoder::decode([
            'Program 4kRC invoke [1]',
            'Program 4kRC failed: custom program error: 0x1',
        ]);

        self::assertSame(DecodedLogEvent::KIND_FAILURE, $events[1]->kind);
        self::assertSame('custom program error: 0x1', $events[1]->error);
    }

    #[Test]
    public function decodes_consumed_units_and_budget(): void
    {
        $events = LogDecoder::decode([
            'Program 11111111111111111111111111111111 consumed 412 of 200000 compute units',
        ]);

        self::assertSame(DecodedLogEvent::KIND_CONSUMED, $events[0]->kind);
        self::assertSame(412, $events[0]->consumed);
        self::assertSame(200_000, $events[0]->budget);
    }

    #[Test]
    public function decodes_return_data_as_binary(): void
    {
        $raw = "\x01\x02\x03\xff";
        $b64 = base64_encode($raw);

        $events = LogDecoder::decode(['Program return: 4kRC ' . $b64]);

        self::assertSame(DecodedLogEvent::KIND_RETURN_DATA, $events[0]->kind);
        self::assertSame('4kRC', $events[0]->programId);
        self::assertSame($raw, $events[0]->bytes);
    }

    #[Test]
    public function decodes_program_data_using_active_invoke_frame(): void
    {
        $raw = 'anchor-event-bytes';
        $b64 = base64_encode($raw);

        $events = LogDecoder::decode([
            'Program ANCHORPROG invoke [1]',
            "Program data: {$b64}",
            'Program ANCHORPROG success',
        ]);

        $data = $events[1];
        self::assertSame(DecodedLogEvent::KIND_DATA, $data->kind);
        self::assertSame('ANCHORPROG', $data->programId);
        self::assertSame($raw, $data->bytes);
    }

    #[Test]
    public function decodes_program_log_using_active_invoke_frame(): void
    {
        $events = LogDecoder::decode([
            'Program PROG invoke [1]',
            'Program log: Instruction: Initialize',
            'Program PROG success',
        ]);

        self::assertSame(DecodedLogEvent::KIND_LOG, $events[1]->kind);
        self::assertSame('PROG', $events[1]->programId);
        self::assertSame('Instruction: Initialize', $events[1]->message);
    }

    #[Test]
    public function tracks_nested_invoke_frames(): void
    {
        $events = LogDecoder::decode([
            'Program OUTER invoke [1]',
            'Program log: outer-log',
            'Program INNER invoke [2]',
            'Program log: inner-log',
            'Program INNER success',
            'Program log: outer-log-again',
            'Program OUTER success',
        ]);

        $programs = array_map(static fn (DecodedLogEvent $e): ?string => $e->programId, $events);
        $messages = array_map(static fn (DecodedLogEvent $e): ?string => $e->message, $events);

        self::assertSame('OUTER', $programs[1]);
        self::assertSame('outer-log', $messages[1]);

        self::assertSame('INNER', $programs[3]);
        self::assertSame('inner-log', $messages[3]);

        // After INNER success the stack pops back to OUTER; the next "Program log:" must attribute to OUTER.
        self::assertSame('OUTER', $programs[5]);
        self::assertSame('outer-log-again', $messages[5]);
    }

    #[Test]
    public function decodes_truncation_marker(): void
    {
        $events = LogDecoder::decode(['Log truncated']);

        self::assertSame(DecodedLogEvent::KIND_TRUNCATED, $events[0]->kind);
        self::assertSame('Log truncated', $events[0]->message);
    }

    #[Test]
    public function unknown_lines_pass_through_as_kind_unknown(): void
    {
        $events = LogDecoder::decode(['this line matches nothing']);

        self::assertSame(DecodedLogEvent::KIND_UNKNOWN, $events[0]->kind);
        self::assertSame('this line matches nothing', $events[0]->message);
    }

    #[Test]
    public function multiple_return_events_in_one_tx(): void
    {
        $events = LogDecoder::decode([
            'Program return: A ' . base64_encode('a'),
            'Program return: B ' . base64_encode('bb'),
        ]);

        $returns = array_filter($events, static fn (DecodedLogEvent $e): bool => $e->kind === DecodedLogEvent::KIND_RETURN_DATA);

        self::assertCount(2, $returns);
    }

    #[Test]
    public function program_log_before_any_invoke_has_null_program_id(): void
    {
        $events = LogDecoder::decode(['Program log: orphan log']);

        self::assertSame(DecodedLogEvent::KIND_LOG, $events[0]->kind);
        self::assertNull($events[0]->programId);
    }
}
