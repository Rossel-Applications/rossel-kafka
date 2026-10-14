<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Factory;

use Enqueue\RdKafka\RdKafkaMessage;
use Rossel\RosselKafka\Enum\MessageHeaders\Area;
use Rossel\RosselKafka\Enum\MessageHeaders\MessageType;
use Rossel\RosselKafka\Model\Message;
use Rossel\RosselKafka\Model\MessageHeaders;
use Rossel\RosselKafka\Utils\ArrayUtils;
use Symfony\Component\OptionsResolver\OptionsResolver;

final readonly class MessageFactory implements MessageFactoryInterface
{
    /**
     * @throws \Exception
     */
    public function createMessageFromRdKafka(RdKafkaMessage $message): Message
    {
        return new Message(
            $this->createMessageHeadersFromRdKafka($message),
            $message->getBody(),
            $message->getKey(),
        );
    }

    /**
     * @throws \Exception
     */
    private function createMessageHeadersFromRdKafka(RdKafkaMessage $message): MessageHeaders
    {
        $headers = $message->getHeaders();

        $this->validateMessageHeaders($headers);

        /** @var ?string $dateTimeString */
        $dateTimeString = ArrayUtils::pull($headers, MessageHeaders::KEY_DATE_TIME);

        /** @var ?string $dateTimeOriginalString */
        $dateTimeOriginalString = ArrayUtils::pull($headers, MessageHeaders::KEY_DATE_TIME_ORIGINAL);

        /** @var int|string $area */
        $area = ArrayUtils::pull($headers, MessageHeaders::KEY_AREA);

        /** @var string $from */
        $from = ArrayUtils::pull($headers, MessageHeaders::KEY_FROM);

        /** @var string $messageType */
        $messageType = ArrayUtils::pull($headers, MessageHeaders::KEY_MESSAGE_TYPE);

        /** @var string|null $trackId */
        $trackId = ArrayUtils::pull($headers, MessageHeaders::KEY_TRACK_ID);

        /** @var string|null $fromOriginal */
        $fromOriginal = ArrayUtils::pull($headers, MessageHeaders::KEY_FROM_ORIGINAL);

        /** @var string|null $trackIdOriginal */
        $trackIdOriginal = ArrayUtils::pull($headers, MessageHeaders::KEY_TRACK_ID_ORIGINAL);

        /** @var string $version */
        $version = ArrayUtils::pull($headers, MessageHeaders::KEY_VERSION);

        /** @var array<string, scalar> $additionalHeaders */
        $additionalHeaders = $headers;

        return new MessageHeaders(
            area: Area::from($area),
            from: $from,
            messageType: MessageType::from($messageType),
            trackId: $trackId,
            dateTime: null === $dateTimeString ? null : new \DateTimeImmutable($dateTimeString),
            dateTimeOriginal: null === $dateTimeOriginalString ? null : new \DateTimeImmutable($dateTimeOriginalString),
            fromOriginal: $fromOriginal,
            trackIdOriginal: $trackIdOriginal,
            version: $version,
            additionalHeaders: $additionalHeaders,
        );
    }

    /**
     * @param array<array-key, mixed> $headers
     */
    private function validateMessageHeaders(array $headers): void
    {
        $optionsResolver = new OptionsResolver();

        $optionsResolver
            ->setIgnoreUndefined()
            ->define(MessageHeaders::KEY_AREA)
                ->allowedTypes('string')
                ->required()
            ->define(MessageHeaders::KEY_FROM)
                ->allowedTypes('string')
                ->required()
            ->define(MessageHeaders::KEY_MESSAGE_TYPE)
                ->allowedTypes('string')
                ->required()
            ->define(MessageHeaders::KEY_TRACK_ID)
                ->allowedTypes('string', 'null')
                ->required()
            ->define(MessageHeaders::KEY_DATE_TIME)
                ->allowedTypes('string', 'null')
                ->required()
            ->define(MessageHeaders::KEY_DATE_TIME_ORIGINAL)
                ->allowedTypes('string', 'null')
                ->required()
            ->define(MessageHeaders::KEY_FROM_ORIGINAL)
                ->allowedTypes('string', 'null')
                ->required()
            ->define(MessageHeaders::KEY_TRACK_ID_ORIGINAL)
                ->allowedTypes('string', 'null')
                ->required();

        $optionsResolver->resolve($headers);
    }
}
