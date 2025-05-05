<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Factory;

use Enqueue\RdKafka\RdKafkaMessage;
use Psr\Log\LoggerInterface;
use Rossel\RosselKafka\Enum\MessageHeaders\Area;
use Rossel\RosselKafka\Enum\MessageHeaders\MessageType;
use Rossel\RosselKafka\Model\Message;
use Rossel\RosselKafka\Model\MessageHeaders;
use Rossel\RosselKafka\Utils\ArrayUtils;
use Symfony\Component\OptionsResolver\OptionsResolver;

final readonly class MessageFactory
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws \JsonException
     * @throws \Exception
     */
    public function createMessageFromRdKafka(RdKafkaMessage $message): Message
    {
        return new Message(
            $this->createMessageHeadersFromRdKafka($message),
            $this->createMessageBodyFromRdKafka($message),
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

        return new MessageHeaders(
            area: Area::from(ArrayUtils::pull($headers, MessageHeaders::KEY_AREA)),
            from: ArrayUtils::pull($headers, MessageHeaders::KEY_FROM),
            messageType: MessageType::from(ArrayUtils::pull($headers, MessageHeaders::KEY_MESSAGE_TYPE)),
            trackId: ArrayUtils::pull($headers, MessageHeaders::KEY_TRACK_ID),
            dateTime: null === $dateTimeString ? null : new \DateTimeImmutable($dateTimeString),
            dateTimeOriginal: null === $dateTimeOriginalString ? null : new \DateTimeImmutable($dateTimeOriginalString),
            fromOriginal: ArrayUtils::pull($headers, MessageHeaders::KEY_FROM_ORIGINAL),
            trackIdOriginal: ArrayUtils::pull($headers, MessageHeaders::KEY_TRACK_ID_ORIGINAL),
            version: ArrayUtils::pull($headers, MessageHeaders::KEY_VERSION),
            additionalHeaders: $headers,
        );
    }

    /**
     * @return string|array<array-key, mixed>
     */
    private function createMessageBodyFromRdKafka(RdKafkaMessage $message): array
    {
        $body = $message->getBody();

        $this->logger->debug('Starting message body serialization...');

        try {
            return json_decode($body, true, \JSON_THROW_ON_ERROR, \JSON_THROW_ON_ERROR);
        } catch (\Exception) {
            $this->logger->debug('Message body cannot be serialized in json format. Returning a string body.');
        }

        return $body;
    }

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
