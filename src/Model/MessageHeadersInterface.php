<?php

declare(strict_types=1);

namespace Rossel\RosselKafkaPhpKit\Model;

use Rossel\RosselKafkaPhpKit\Enum\MessageHeaders\Area;
use Rossel\RosselKafkaPhpKit\Enum\MessageHeaders\MessageType;

interface MessageHeadersInterface
{
    public function getArea(): Area;

    public function getDateTime(): \DateTimeImmutable;

    public function getDateTimeOriginal(): \DateTimeImmutable;

    public function getFrom(): string;

    public function getFromOriginal(): string;

    public function getMessageType(): MessageType;

    public function getTrackId(): string;

    public function getTrackIdOriginal(): string;

    public function getVersion(): string;

    /**
     * @return array<string, scalar>
     */
    public function toArray(): array;
}
