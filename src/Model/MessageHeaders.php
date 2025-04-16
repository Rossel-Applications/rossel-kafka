<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Model;

use Ramsey\Uuid\Uuid;
use Rossel\RosselKafka\Enum\MessageHeaders\Area;
use Rossel\RosselKafka\Enum\MessageHeaders\MessageType;

final readonly class MessageHeaders implements MessageHeadersInterface
{
    public const DEFAULT_VERSION = '1';

    public const KEY_AREA = 'area';
    public const KEY_DATE_TIME = 'dateTime';
    public const KEY_DATE_TIME_ORIGINAL = 'dateTimeOriginal';
    public const KEY_FROM = 'from';
    public const KEY_FROM_ORIGINAL = 'fromOriginal';
    public const KEY_MESSAGE_TYPE = 'messageType';
    public const KEY_TRACK_ID = 'trackId';
    public const KEY_TRACK_ID_ORIGINAL = 'trackIdOriginal';
    public const KEY_VERSION = 'version';

    private \DateTimeImmutable $dateTime;

    private string $trackId;

    /**
     * Initializes the message with the provided headers.
     *
     * @param Area                    $area              the functional area associated with the message
     * @param string                  $from              the originating application of the message
     * @param MessageType             $messageType       the message type
     * @param string|null             $trackId           Optional unique identifier for tracking the message. If null, a new UUID will be generated.
     * @param \DateTimeImmutable|null $dateTime          Optional timestamp for the message. If null, the current time is used.
     * @param \DateTimeImmutable|null $dateTimeOriginal  Optional original timestamp for the message. Defaults to the value of `$dateTime` if null.
     * @param string|null             $fromOriginal      Optional identifier for the original application that issued the message. Defaults to `$from` if null.
     * @param string|null             $trackIdOriginal   Optional identifier for the original track ID. Defaults to `$trackId` if null.
     * @param array<string, scalar>   $additionalHeaders array of additional headers
     */
    public function __construct(
        private Area $area,
        private string $from,
        private MessageType $messageType,
        ?string $trackId = null,
        ?\DateTimeImmutable $dateTime = null,
        private ?\DateTimeImmutable $dateTimeOriginal = null,
        private ?string $fromOriginal = null,
        private ?string $trackIdOriginal = null,
        private string $version = self::DEFAULT_VERSION,
        private array $additionalHeaders = [],
    ) {
        $this->dateTime = $dateTime ?? new \DateTimeImmutable();
        $this->trackId = $trackId ?? $this->generateTrackId();
    }

    /**
     * @return array<string, scalar>
     */
    public function toArray(): array
    {
        return [
            self::KEY_AREA => $this->getArea()->value,
            self::KEY_DATE_TIME => $this->getDateTime()->format(\DateTimeInterface::ATOM),
            self::KEY_DATE_TIME_ORIGINAL => $this->getDateTimeOriginal()->format(\DateTimeInterface::ATOM),
            self::KEY_FROM => $this->getFrom(),
            self::KEY_FROM_ORIGINAL => $this->getFromOriginal(),
            self::KEY_MESSAGE_TYPE => $this->getMessageType()->name,
            self::KEY_TRACK_ID => $this->getTrackId(),
            self::KEY_TRACK_ID_ORIGINAL => $this->getTrackIdOriginal(),
            self::KEY_VERSION => $this->getVersion(),
        ];
    }

    public function getArea(): Area
    {
        return $this->area;
    }

    public function getDateTime(): \DateTimeImmutable
    {
        return $this->dateTime;
    }

    public function getDateTimeOriginal(): \DateTimeImmutable
    {
        return $this->dateTimeOriginal ?? $this->getDateTime();
    }

    public function getFrom(): string
    {
        return $this->from;
    }

    public function getFromOriginal(): string
    {
        return $this->fromOriginal ?? $this->getFrom();
    }

    public function getMessageType(): MessageType
    {
        return $this->messageType;
    }

    public function getTrackId(): string
    {
        return $this->trackId;
    }

    public function getTrackIdOriginal(): string
    {
        return $this->trackIdOriginal ?? $this->getTrackId();
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * @return array<string, scalar>
     */
    public function getAdditionalHeaders(): array
    {
        return $this->additionalHeaders;
    }

    private function generateTrackId(): string
    {
        return (string) Uuid::uuid4();
    }
}
