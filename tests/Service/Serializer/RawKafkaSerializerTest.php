<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Tests\Service\Serializer;

use Enqueue\RdKafka\RdKafkaMessage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rossel\RosselKafka\Service\Serializer\RawKafkaSerializer;

final class RawKafkaSerializerTest extends TestCase
{
    private RawKafkaSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new RawKafkaSerializer();
    }

    #[Test]
    public function toStringReturnsBodyVerbatim(): void
    {
        $payload = '{"data":{"subscriptionNumber":"711307322"},"source":"ERPA_SHIPO","sourceId":"712000408"}';
        $message = new RdKafkaMessage($payload, [], ['area' => 'FR', 'from' => 'ERPA']);

        self::assertSame($payload, $this->serializer->toString($message));
    }

    #[Test]
    public function toStringDoesNotWrapBodyInEnvelope(): void
    {
        $message = new RdKafkaMessage('plain text', [], ['area' => 'FR']);

        $serialized = $this->serializer->toString($message);

        self::assertSame('plain text', $serialized);
        self::assertStringNotContainsString('"body"', $serialized);
        self::assertStringNotContainsString('"headers"', $serialized);
    }

    #[Test]
    public function toMessageWrapsRawValueAsBody(): void
    {
        $payload = '{"data":{"foo":"bar"}}';

        $message = $this->serializer->toMessage($payload);

        self::assertSame($payload, $message->getBody());
        self::assertSame([], $message->getProperties());
        self::assertSame([], $message->getHeaders());
    }

    #[Test]
    public function toMessageAcceptsNonJsonPayload(): void
    {
        $message = $this->serializer->toMessage('EXEC_SUCCESS');

        self::assertSame('EXEC_SUCCESS', $message->getBody());
    }

    #[Test]
    public function toMessageAcceptsEmptyPayload(): void
    {
        $message = $this->serializer->toMessage('');

        self::assertSame('', $message->getBody());
    }

    #[Test]
    public function roundTripPreservesPayload(): void
    {
        $payload = '{"data":{"subscriptionNumber":"711307322"}}';

        $rebuilt = $this->serializer->toString($this->serializer->toMessage($payload));

        self::assertSame($payload, $rebuilt);
    }
}
