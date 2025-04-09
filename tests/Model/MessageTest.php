<?php

namespace Model;

use JsonException;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Rossel\RosselKafka\Enum\MessageHeaders\MessageType;
use Rossel\RosselKafka\Model\Message;
use Rossel\RosselKafka\Model\MessageHeadersInterface;

final class MessageTest extends TestCase
{
    /**
     * Test that getType() correctly returns the message type from the headers.
     *
     * @throws Exception
     * @throws JsonException
     */
    public function testGetTypeReturnsCorrectMessageType(): void
    {
        $headers = $this->createMock(MessageHeadersInterface::class);
        $headers->method('getMessageType')->willReturn(MessageType::SYNC_B2C_ERP_SUBSCRIPTION);

        $message = new Message(
            headers: $headers,
            body: ['key' => 'value']
        );

        self::assertSame(MessageType::SYNC_B2C_ERP_SUBSCRIPTION, $message->getType());
    }

    /**
     * Test that getRdKafkaMessage() correctly returns a valid RdKafkaMessage
     * with the properly encoded body and headers.
     *
     * @throws Exception
     * @throws JsonException
     */
    public function testGetRdKafkaMessageReturnsValidRdKafkaMessage(): void
    {
        $headers = $this->createMock(MessageHeadersInterface::class);
        $headers->method('getMessageType')->willReturn(MessageType::SYNC_B2C_ERP_SUBSCRIPTION);
        $headers->method('toArray')->willReturn(['header-key' => 'header-value']);

        $body = ['foo' => 'bar'];

        $message = new Message(
            headers: $headers,
            body: $body
        );

        $rdKafkaMessage = $message->getRdKafkaMessage();

        self::assertJsonStringEqualsJsonString(json_encode($body, JSON_THROW_ON_ERROR), $rdKafkaMessage->getBody());
        self::assertSame(['header-key' => 'header-value'], $rdKafkaMessage->getHeaders());
    }

    /**
     * Test that the constructor throws a JsonException when the body
     * contains non-encodable data.
     *
     * @throws Exception
     */
    public function testConstructorThrowsExceptionOnInvalidJson(): void
    {
        $this->expectException(JsonException::class);

        $headers = $this->createMock(MessageHeadersInterface::class);
        $headers->method('getMessageType')->willReturn(MessageType::SYNC_B2C_ERP_SUBSCRIPTION);
        $headers->method('toArray')->willReturn(['header' => 'value']);

        // Using a resource in the body which cannot be JSON encoded
        $invalidBody = ['invalid' => tmpfile()];

        new Message(
            headers: $headers,
            body: $invalidBody
        );
    }
}
