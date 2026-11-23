<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Tests\Model;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rossel\RosselKafka\Enum\MessageHeaders\Area;
use Rossel\RosselKafka\Enum\MessageHeaders\MessageType;
use Rossel\RosselKafka\Model\Message;
use Rossel\RosselKafka\Model\MessageHeaders;
use Rossel\RosselKafka\Model\MessageInterface;

final class MessageTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Body handling
    // -------------------------------------------------------------------------

    #[Test]
    public function stringBodyKeptAsIs(): void
    {
        $message = new Message($this->buildHeaders(), 'plain text body');

        self::assertSame('plain text body', $message->getBody());
    }

    #[Test]
    public function arrayBodyEncodedToJson(): void
    {
        $message = new Message($this->buildHeaders(), ['key' => 'value', 'num' => 42]);

        self::assertSame('{"key":"value","num":42}', $message->getBody());
    }

    #[Test]
    public function emptyArrayBodyEncodedToEmptyJsonObject(): void
    {
        $message = new Message($this->buildHeaders(), []);

        self::assertSame('[]', $message->getBody());
    }

    #[Test]
    public function nestedArrayBodyEncodedCorrectly(): void
    {
        $body = ['level1' => ['level2' => 'deep']];
        $message = new Message($this->buildHeaders(), $body);

        self::assertSame('{"level1":{"level2":"deep"}}', $message->getBody());
    }

    // -------------------------------------------------------------------------
    // getType
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
    }

    #[Test]
    #[DataProvider('messageTypeProvider')]
    public function getTypeDelegatesToHeaders(MessageType $type): void
    {
        $headers = new MessageHeaders(
            area: Area::FRANCE,
            from: 'app',
            messageType: $type,
        );
        $message = new Message($headers, 'body');

        self::assertSame($type, $message->getType());
    }

    // -------------------------------------------------------------------------
    // getHeaders
    // -------------------------------------------------------------------------

    #[Test]
    public function shouldExposeHeadersAsKafkaHeaders(): void
    {
        $headers = new MessageHeaders(
            area: Area::BELGIUM,
            from: 'svc',
            messageType: MessageType::EXEC_SUCCESS,
            trackId: 'tid-abc',
        );
        $message = new Message($headers, '{}');

        $kafkaHeaders = $message->getHeaders();

        self::assertSame($headers->toArray(), $kafkaHeaders);
    }

    // -------------------------------------------------------------------------
    // Key
    // -------------------------------------------------------------------------

    #[Test]
    public function keyIsNullByDefault(): void
    {
        $message = new Message($this->buildHeaders(), 'body');

        self::assertNull($message->getKey());
    }

    #[Test]
    public function shouldExposeKey(): void
    {
        $message = new Message($this->buildHeaders(), 'body', 'subscription-42');

        self::assertSame('subscription-42', $message->getKey());
    }

    // -------------------------------------------------------------------------
    // MessageInterface
    // -------------------------------------------------------------------------

    /**
     * @return iterable<string, array{string}>
     */
    public static function interfaceMethodProvider(): iterable
    {
        yield 'getBody' => ['getBody'];
        yield 'getHeaders' => ['getHeaders'];
    }

    #[Test]
    #[DataProvider('interfaceMethodProvider')]
    public function shouldDeclareWhatTheConnectorSendsOnMessageInterface(string $method): void
    {
        $interface = new \ReflectionClass(MessageInterface::class);

        $declaringClass = $interface->getMethod($method)->getDeclaringClass()->getName();

        self::assertSame(MessageInterface::class, $declaringClass);
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    private function buildHeaders(MessageType $messageType = MessageType::LOG): MessageHeaders
    {
        return new MessageHeaders(
            area: Area::FRANCE,
            from: 'test-app',
            messageType: $messageType,
        );
    }
}
