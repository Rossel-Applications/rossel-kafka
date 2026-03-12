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

final readonly class MessageFactory implements MessageFactoryInterface
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

        /* @var array<string, scalar> $headers */
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
            additionalHeaders: $headers,
        );
    }

    /**
     * @return array<array-key, mixed>|string
     */
    private function createMessageBodyFromRdKafka(RdKafkaMessage $message): array|string
    {
        $body = $message->getBody();

        $this->logger->debug('Starting message body serialization...');

        try {
            $decoded = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
            // assert() narrows the type for PHPStan without an inline @var that CS Fixer would strip.
            \assert(\is_array($decoded) || \is_string($decoded));

            return $decoded;
        } catch (\Exception) {
            $this->logger->debug('Message body cannot be serialized in json format. Returning a string body.');
        }

        return $body;
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
