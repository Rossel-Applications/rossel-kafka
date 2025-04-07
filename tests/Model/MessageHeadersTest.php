<?php
namespace Model;

use DateTimeImmutable;
use DateTimeInterface;
use PHPUnit\Framework\TestCase;
use Rossel\RosselKafka\Enum\MessageHeaders\Area;
use Rossel\RosselKafka\Enum\MessageHeaders\MessageType;
use Rossel\RosselKafka\Model\MessageHeaders;

final class MessageHeadersTest extends TestCase
{
    /**
     * Test that the minimal constructor correctly sets default values
     * for trackId, dateTime, and other optional fields.
     */
    public function testMinimalConstructor(): void
    {
        $headers = new MessageHeaders(
            area: Area::cases()[0],
            from: 'app-test',
            messageType: MessageType::cases()[0]
        );

        self::assertSame(Area::cases()[0], $headers->getArea());
        self::assertSame('app-test', $headers->getFrom());
        self::assertSame(MessageType::cases()[0], $headers->getMessageType());
        self::assertSame(MessageHeaders::DEFAULT_VERSION, $headers->getVersion());

        // Auto-generation of trackId and trackIdOriginal
        self::assertNotEmpty($headers->getTrackId());
        self::assertNotEmpty($headers->getTrackIdOriginal());
        self::assertSame($headers->getTrackId(), $headers->getTrackIdOriginal());

        // Default dateTime should be close to now
        $now = new DateTimeImmutable();
        self::assertEqualsWithDelta($now->getTimestamp(), $headers->getDateTime()->getTimestamp(), 2);
        self::assertEquals($headers->getDateTime(), $headers->getDateTimeOriginal());

        // Default fromOriginal should be equal to from
        self::assertSame('app-test', $headers->getFromOriginal());
    }

    /**
     * Test that all fields are correctly set when the full constructor is used,
     * including optional overrides like dateTimeOriginal and trackIdOriginal.
     */
    public function testFullConstructor(): void
    {
        $dateTime = new DateTimeImmutable('2024-04-01T12:00:00Z');
        $dateTimeOriginal = new DateTimeImmutable('2024-03-01T12:00:00Z');

        $headers = new MessageHeaders(
            area: Area::cases()[0],
            from: 'app-prod',
            messageType: MessageType::cases()[0],
            trackId: 'test-track-id',
            dateTime: $dateTime,
            dateTimeOriginal: $dateTimeOriginal,
            fromOriginal: 'app-original',
            trackIdOriginal: 'original-track-id',
            version: '2'
        );

        self::assertSame(Area::cases()[0], $headers->getArea());
        self::assertSame('app-prod', $headers->getFrom());
        self::assertSame(MessageType::cases()[0], $headers->getMessageType());
        self::assertSame('test-track-id', $headers->getTrackId());
        self::assertSame('original-track-id', $headers->getTrackIdOriginal());
        self::assertSame('2', $headers->getVersion());

        self::assertSame($dateTime, $headers->getDateTime());
        self::assertSame($dateTimeOriginal, $headers->getDateTimeOriginal());
        self::assertSame('app-original', $headers->getFromOriginal());
    }

    /**
     * Test that the toArray() method returns all the expected key-value pairs
     * correctly formatted, including dates and enums.
     */
    public function testToArray(): void
    {
        $dateTime = new DateTimeImmutable('2025-04-07T12:00:00Z');

        $headers = new MessageHeaders(
            area: Area::cases()[0],
            from: 'app-api',
            messageType: MessageType::cases()[0],
            trackId: 'test-track-id',
            dateTime: $dateTime,
            version: '1'
        );

        $expected = [
            'area' => Area::cases()[0]->value,
            'dateTime' => $dateTime->format(DateTimeInterface::ATOM),
            'dateTimeOriginal' => $dateTime->format(DateTimeInterface::ATOM),
            'from' => 'app-api',
            'fromOriginal' => 'app-api',
            'messageType' => MessageType::cases()[0]->name,
            'trackId' => 'test-track-id',
            'trackIdOriginal' => 'test-track-id',
            'version' => '1',
        ];

        self::assertSame($expected, $headers->toArray());
    }
}
