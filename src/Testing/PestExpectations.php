<?php declare(strict_types=1);

namespace SanderMuller\SolanaPhpSdk\Testing;

use Closure;
use Pest\Expectation;
use PHPUnit\Framework\Assert;
use SanderMuller\SolanaPhpSdk\DataObjects\SignatureStatus;
use SanderMuller\SolanaPhpSdk\Errors\TransactionError;
use SanderMuller\SolanaPhpSdk\Exceptions\SendTransactionError;
use SanderMuller\SolanaPhpSdk\Util\Commitment;

/**
 * Pest expectation extensions for Solana flows. Call
 * {@see PestExpectations::register()} once from `tests/Pest.php` to wire
 * the macros — the package does not auto-register them so suites that
 * use plain PHPUnit are unaffected.
 *
 * Available expectations (after registration):
 *
 *  - `expect($status)->toBeConfirmed(?Commitment $atLeast = null)` — assert
 *     a {@see SignatureStatus} reached at least the given commitment.
 *  - `expect($error)->toHaveCustomCode(int $code)` — assert a
 *     {@see SendTransactionError} carries an `InstructionError(Custom(...))`
 *     with the given code.
 *
 * The functions are wrapped in a Pest-availability guard so static
 * analysis does not complain when the dev dependency is absent.
 *
 * @api
 */
final class PestExpectations
{
    public $value;

    public static function register(): void
    {
        if (! function_exists('expect')) {
            return;
        }

        /** @var Closure(string|callable, callable): mixed $extend */
        $extend = static function (string $name, callable $body): void {
            \expect()->extend($name, $body);
        };

        $extend('toBeConfirmed', function (?Commitment $atLeast = null): mixed {
            /** @var Expectation $this */
            $status = $this->value;

            if (! $status instanceof SignatureStatus) {
                Assert::fail(sprintf(
                    'toBeConfirmed() expects a SignatureStatus, got %s',
                    get_debug_type($status),
                ));
            }

            $target = $atLeast ?? Commitment::confirmed();
            $confirmed = $status->reachedAtLeast($target);
            Assert::assertTrue(
                $confirmed,
                sprintf(
                    'Failed asserting signature reached commitment "%s"; saw "%s".',
                    $target->commitmentLevel,
                    $status->confirmationStatus ?? 'unknown',
                ),
            );

            return $this;
        });

        $extend('toHaveCustomCode', function (int $code): mixed {
            /** @var Expectation $this */
            $error = $this->value;

            if (! $error instanceof SendTransactionError) {
                Assert::fail(sprintf(
                    'toHaveCustomCode() expects a SendTransactionError, got %s',
                    get_debug_type($error),
                ));
            }

            $actual = $error->transactionError->customCode();
            Assert::assertSame(
                $code,
                $actual,
                sprintf(
                    'Failed asserting transaction error carries Custom(%d); saw %s.',
                    $code,
                    $actual === null ? 'null' : "Custom({$actual})",
                ),
            );

            return $this;
        });

        $extend('toBeInstructionError', function (?int $atIndex = null): mixed {
            /** @var Expectation $this */
            $value = $this->value;

            $error = $value instanceof SendTransactionError ? $value->transactionError : $value;

            if (! $error instanceof TransactionError) {
                Assert::fail(sprintf(
                    'toBeInstructionError() expects a TransactionError, got %s',
                    get_debug_type($value),
                ));
            }

            Assert::assertTrue(
                $error->isInstructionError(),
                "Failed asserting TransactionError is an InstructionError; saw {$error->kind}.",
            );

            if ($atIndex !== null) {
                Assert::assertSame(
                    $atIndex,
                    $error->instructionIndex,
                    "Failed asserting InstructionError sits at index {$atIndex}.",
                );
            }

            return $this;
        });
    }
}
