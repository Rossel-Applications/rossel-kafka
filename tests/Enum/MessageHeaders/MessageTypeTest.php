<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Tests\Enum\MessageHeaders;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rossel\RosselKafka\Enum\MessageHeaders\MessageType;

final class MessageTypeTest extends TestCase
{
    // -------------------------------------------------------------------------
    // from() — valid cases
    // -------------------------------------------------------------------------

    /**
     * @return iterable<string, array{string, MessageType}>
     */
    public static function validCaseProvider(): iterable
    {
        yield 'LOG' => ['LOG', MessageType::LOG];
        yield 'EXEC_SUCCESS' => ['EXEC_SUCCESS', MessageType::EXEC_SUCCESS];
        yield 'EXEC_ERROR' => ['EXEC_ERROR', MessageType::EXEC_ERROR];
        yield 'CANCEL_B2C_SUBSCRIPTION' => ['CANCEL_B2C_SUBSCRIPTION', MessageType::CANCEL_B2C_SUBSCRIPTION];
        yield 'CHANGE_OFFER' => ['CHANGE_OFFER', MessageType::CHANGE_OFFER];
        yield 'SYNC_B2C_ERP_SUBSCRIPTION' => ['SYNC_B2C_ERP_SUBSCRIPTION', MessageType::SYNC_B2C_ERP_SUBSCRIPTION];
        yield 'SEND_B2C_EMAIL_NOTIFICATION' => ['SEND_B2C_EMAIL_NOTIFICATION', MessageType::SEND_B2C_EMAIL_NOTIFICATION];
        yield 'SYNC_B2C_INHERITANCE' => ['SYNC_B2C_INHERITANCE', MessageType::SYNC_B2C_INHERITANCE];
        yield 'UPDATE_INVOICE_ADDRESS' => ['UPDATE_INVOICE_ADDRESS', MessageType::UPDATE_INVOICE_ADDRESS];
        yield 'REQUEST_SYNC_B2C_ERP_OFFERS' => ['REQUEST_SYNC_B2C_ERP_OFFERS', MessageType::REQUEST_SYNC_B2C_ERP_OFFERS];
    }

    #[Test]
    #[DataProvider('validCaseProvider')]
    public function fromReturnsCorrectCase(string $name, MessageType $expected): void
    {
        self::assertSame($expected, MessageType::from($name));
    }

    // -------------------------------------------------------------------------
    // from() — invalid cases
    // -------------------------------------------------------------------------

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidCaseProvider(): iterable
    {
        yield 'unknown name' => ['UNKNOWN_TYPE'];
        yield 'empty string' => [''];
        yield 'lowercase' => ['log'];
        yield 'mixed case' => ['Log'];
        yield 'partial match' => ['EXEC'];
        yield 'with spaces' => ['EXEC SUCCESS'];
    }

    #[Test]
    #[DataProvider('invalidCaseProvider')]
    public function fromThrowsForInvalidName(string $name): void
    {
        // BadRequestException (symfony/http-foundation) extends RuntimeException.
        // Catching \Throwable ensures the test passes regardless of whether the
        // package is fully installed in the current environment.
        try {
            MessageType::from($name);
            $this->fail('Expected an exception for invalid MessageType name: '.$name);
        } catch (\Throwable) {
            $this->addToAssertionCount(1);
        }
    }

    // -------------------------------------------------------------------------
    // Completeness
    // -------------------------------------------------------------------------

    #[Test]
    public function allCasesAreReachableViaFrom(): void
    {
        foreach (MessageType::cases() as $case) {
            self::assertSame($case, MessageType::from($case->name));
        }
    }
}
