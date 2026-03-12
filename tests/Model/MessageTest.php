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
    // getRdKafkaMessage
    // -------------------------------------------------------------------------

    #[Test]
    public function rdKafkaMessageHasCorrectBody(): void
    {
        $message = new Message($this->buildHeaders(), 'test-body');

        self::assertSame('test-body', $message->getRdKafkaMessage()->getBody());
    }

    #[Test]
    public function rdKafkaMessageHasHeadersFromMessageHeaders(): void
    {
        $headers = new MessageHeaders(
            area: Area::BELGIUM,
            from: 'svc',
            messageType: MessageType::EXEC_SUCCESS,
            trackId: 'tid-abc',
        );
        $message = new Message($headers, '{}');

        $rdHeaders = $message->getRdKafkaMessage()->getHeaders();

        self::assertSame('BE', $rdHeaders[MessageHeaders::KEY_AREA]);
        self::assertSame('svc', $rdHeaders[MessageHeaders::KEY_FROM]);
        self::assertSame('EXEC_SUCCESS', $rdHeaders[MessageHeaders::KEY_MESSAGE_TYPE]);
        self::assertSame('tid-abc', $rdHeaders[MessageHeaders::KEY_TRACK_ID]);
    }

    #[Test]
    public function rdKafkaMessageBodyMatchesGetBody(): void
    {
        $message = new Message($this->buildHeaders(), ['foo' => 'bar']);

        self::assertSame($message->getBody(), $message->getRdKafkaMessage()->getBody());
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
