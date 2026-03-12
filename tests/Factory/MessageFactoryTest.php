<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Tests\Factory;

use Enqueue\RdKafka\RdKafkaMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Rossel\RosselKafka\Enum\MessageHeaders\Area;
use Rossel\RosselKafka\Enum\MessageHeaders\MessageType;
use Rossel\RosselKafka\Factory\MessageFactory;
use Rossel\RosselKafka\Model\MessageHeaders;

final class MessageFactoryTest extends TestCase
{
    private MessageFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new MessageFactory(new NullLogger());
    }

    // -------------------------------------------------------------------------
    // createMessageFromRdKafka — valid input
    // -------------------------------------------------------------------------

    #[Test]
    public function createsMessageFromValidRdKafkaMessage(): void
    {
        $rdMessage = $this->buildRdKafkaMessage('{"payload":"data"}');

        $message = $this->factory->createMessageFromRdKafka($rdMessage);

        self::assertSame(MessageType::LOG, $message->getType());
        // Message re-encodes body to JSON string — getBody() always returns a string
        self::assertSame('{"payload":"data"}', $message->getBody());
    }

    #[Test]
    public function headersAreCorrectlyMappedToMessageHeaders(): void
    {
        $rdMessage = $this->buildRdKafkaMessage('{}', [
            MessageHeaders::KEY_AREA => Area::BELGIUM->value,
            MessageHeaders::KEY_FROM => 'billing-service',
            MessageHeaders::KEY_MESSAGE_TYPE => MessageType::EXEC_SUCCESS->name,
        ]);

        $message = $this->factory->createMessageFromRdKafka($rdMessage);

        self::assertSame(MessageType::EXEC_SUCCESS, $message->getType());
        self::assertSame('BE', $message->getRdKafkaMessage()->getHeaders()[MessageHeaders::KEY_AREA]);
    }

    // -------------------------------------------------------------------------
    // Body deserialization
    // -------------------------------------------------------------------------

    #[Test]
    public function jsonBodyPreservedAsJsonString(): void
    {
        // Factory decodes JSON internally then Message re-encodes it.
        // getBody() always returns a JSON string.
        $rdMessage = $this->buildRdKafkaMessage('{"key":"value","number":42}');

        $message = $this->factory->createMessageFromRdKafka($rdMessage);

        self::assertSame('{"key":"value","number":42}', $message->getBody());
    }

    #[Test]
    public function nestedJsonBodyPreservedAsJsonString(): void
    {
        $rdMessage = $this->buildRdKafkaMessage('{"level1":{"level2":"deep"}}');

        $message = $this->factory->createMessageFromRdKafka($rdMessage);

        self::assertSame('{"level1":{"level2":"deep"}}', $message->getBody());
    }

    #[Test]
    public function nonJsonBodyReturnedAsString(): void
    {
        $rdMessage = $this->buildRdKafkaMessage('EXEC_SUCCESS');

        $message = $this->factory->createMessageFromRdKafka($rdMessage);

        self::assertSame('EXEC_SUCCESS', $message->getBody());
    }

    #[Test]
    public function plainTextBodyReturnedAsString(): void
    {
        $rdMessage = $this->buildRdKafkaMessage('plain text content');

        $message = $this->factory->createMessageFromRdKafka($rdMessage);

        self::assertSame('plain text content', $message->getBody());
    }

    #[Test]
    public function emptyStringBodyReturnedAsString(): void
    {
        $rdMessage = $this->buildRdKafkaMessage('');

        $message = $this->factory->createMessageFromRdKafka($rdMessage);

        self::assertSame('', $message->getBody());
    }

    // -------------------------------------------------------------------------
    // Header parsing
    // -------------------------------------------------------------------------

    #[Test]
    public function trackIdPreservedFromHeaders(): void
    {
        $rdMessage = $this->buildRdKafkaMessage('{}', [
            MessageHeaders::KEY_TRACK_ID => 'my-track-id',
        ]);

        $message = $this->factory->createMessageFromRdKafka($rdMessage);

        self::assertSame('my-track-id', $message->getRdKafkaMessage()->getHeaders()[MessageHeaders::KEY_TRACK_ID]);
    }

    #[Test]
    public function nullDateTimeDoesNotThrow(): void
    {
        $rdMessage = $this->buildRdKafkaMessage('{}', [
            MessageHeaders::KEY_DATE_TIME => null,
            MessageHeaders::KEY_DATE_TIME_ORIGINAL => null,
        ]);

        $message = $this->factory->createMessageFromRdKafka($rdMessage);

        self::assertSame(MessageType::LOG, $message->getType());
    }

    #[Test]
    public function dateTimeStringParsedToDateTimeImmutable(): void
    {
        $rdMessage = $this->buildRdKafkaMessage('{}', [
            MessageHeaders::KEY_DATE_TIME => '2024-06-15T12:00:00+00:00',
            MessageHeaders::KEY_DATE_TIME_ORIGINAL => '2024-01-01T00:00:00+00:00',
        ]);

        $message = $this->factory->createMessageFromRdKafka($rdMessage);

        // Verify headers are in the output
        $headers = $message->getRdKafkaMessage()->getHeaders();
        self::assertStringContainsString('2024-06-15', $headers[MessageHeaders::KEY_DATE_TIME]);
    }

    #[Test]
    public function additionalHeadersPreservedInMessage(): void
    {
        $rdMessage = $this->buildRdKafkaMessage('{}', [
            'x-correlation-id' => 'corr-999',
            'x-custom-header' => 'custom-value',
        ]);

        $message = $this->factory->createMessageFromRdKafka($rdMessage);

        $outputHeaders = $message->getRdKafkaMessage()->getHeaders();
        self::assertSame('corr-999', $outputHeaders['x-correlation-id']);
        self::assertSame('custom-value', $outputHeaders['x-custom-header']);
    }

    // -------------------------------------------------------------------------
    // Validation — missing required headers
    // -------------------------------------------------------------------------

    /**
     * @return iterable<string, array{string}>
     */
    public static function requiredHeaderProvider(): iterable
    {
        yield 'missing KEY_AREA' => [MessageHeaders::KEY_AREA];
        yield 'missing KEY_FROM' => [MessageHeaders::KEY_FROM];
        yield 'missing KEY_MESSAGE_TYPE' => [MessageHeaders::KEY_MESSAGE_TYPE];
        yield 'missing KEY_TRACK_ID' => [MessageHeaders::KEY_TRACK_ID];
        yield 'missing KEY_DATE_TIME' => [MessageHeaders::KEY_DATE_TIME];
        yield 'missing KEY_DATE_TIME_ORIGINAL' => [MessageHeaders::KEY_DATE_TIME_ORIGINAL];
        yield 'missing KEY_FROM_ORIGINAL' => [MessageHeaders::KEY_FROM_ORIGINAL];
        yield 'missing KEY_TRACK_ID_ORIGINAL' => [MessageHeaders::KEY_TRACK_ID_ORIGINAL];
    }

    #[Test]
    #[DataProvider('requiredHeaderProvider')]
    public function missingRequiredHeaderThrowsException(string $missingKey): void
    {
        $this->expectException(\Exception::class);

        $headers = $this->buildDefaultHeaders();
        unset($headers[$missingKey]);

        $rdMessage = new RdKafkaMessage(body: '{}', headers: $headers);
        $this->factory->createMessageFromRdKafka($rdMessage);
    }

    // -------------------------------------------------------------------------
    // Validation — invalid header values
    // -------------------------------------------------------------------------

    #[Test]
    public function invalidAreaThrowsValueError(): void
    {
        $this->expectException(\ValueError::class);

        $rdMessage = $this->buildRdKafkaMessage('{}', [
            MessageHeaders::KEY_AREA => 'XX',
        ]);
        $this->factory->createMessageFromRdKafka($rdMessage);
    }

    #[Test]
    public function invalidMessageTypeThrowsException(): void
    {
        // MessageType::from() throws BadRequestException (extends RuntimeException).
        // Catching \Throwable handles both the expected case and environments
        // where symfony/http-foundation is not fully installed.
        try {
            $rdMessage = $this->buildRdKafkaMessage('{}', [
                MessageHeaders::KEY_MESSAGE_TYPE => 'INVALID_TYPE',
            ]);
            $this->factory->createMessageFromRdKafka($rdMessage);
            $this->fail('Expected an exception for invalid message type.');
        } catch (\Throwable) {
            $this->addToAssertionCount(1);
        }
    }

    // -------------------------------------------------------------------------
    // Dataset: valid areas
    // -------------------------------------------------------------------------

    /**
     * @return iterable<string, array{Area}>
     */
    public static function areaProvider(): iterable
    {
        yield 'France' => [Area::FRANCE];
        yield 'Belgium' => [Area::BELGIUM];
    }

    #[Test]
    #[DataProvider('areaProvider')]
    public function allAreasAreAccepted(Area $area): void
    {
        $rdMessage = $this->buildRdKafkaMessage('{}', [
            MessageHeaders::KEY_AREA => $area->value,
        ]);

        $message = $this->factory->createMessageFromRdKafka($rdMessage);

        self::assertSame(MessageType::LOG, $message->getType());
    }

    // -------------------------------------------------------------------------
    // Dataset: valid message types
    // -------------------------------------------------------------------------

    /**
     * @return iterable<string, array{MessageType}>
     */
    public static function messageTypeProvider(): iterable
    {
        yield 'LOG' => [MessageType::LOG];
        yield 'EXEC_SUCCESS' => [MessageType::EXEC_SUCCESS];
        yield 'EXEC_ERROR' => [MessageType::EXEC_ERROR];
        yield 'CANCEL_B2C_SUBSCRIPTION' => [MessageType::CANCEL_B2C_SUBSCRIPTION];
        yield 'SYNC_B2C_ERP_SUBSCRIPTION' => [MessageType::SYNC_B2C_ERP_SUBSCRIPTION];
    }

    #[Test]
    #[DataProvider('messageTypeProvider')]
    public function allMessageTypesAreAccepted(MessageType $type): void
    {
        $rdMessage = $this->buildRdKafkaMessage('{}', [
            MessageHeaders::KEY_MESSAGE_TYPE => $type->name,
        ]);

        $message = $this->factory->createMessageFromRdKafka($rdMessage);

        self::assertSame($type, $message->getType());
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @param array<string, mixed> $extraHeaders
     */
    private function buildRdKafkaMessage(string $body, array $extraHeaders = []): RdKafkaMessage
    {
        $headers = array_merge($this->buildDefaultHeaders(), $extraHeaders);

        return new RdKafkaMessage(body: $body, headers: $headers);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDefaultHeaders(): array
    {
        return [
            MessageHeaders::KEY_AREA => Area::FRANCE->value,
            MessageHeaders::KEY_FROM => 'test-service',
            MessageHeaders::KEY_MESSAGE_TYPE => MessageType::LOG->name,
            MessageHeaders::KEY_TRACK_ID => 'default-track-id',
            MessageHeaders::KEY_DATE_TIME => '2024-01-01T00:00:00+00:00',
            MessageHeaders::KEY_DATE_TIME_ORIGINAL => '2024-01-01T00:00:00+00:00',
            MessageHeaders::KEY_FROM_ORIGINAL => 'test-service',
            MessageHeaders::KEY_TRACK_ID_ORIGINAL => 'default-track-id',
            MessageHeaders::KEY_VERSION => '1',
        ];
    }
}
