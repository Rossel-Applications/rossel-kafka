<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Service\Connector;

use Enqueue\RdKafka\RdKafkaConnectionFactory;
use Enqueue\RdKafka\RdKafkaConsumer;
use Enqueue\RdKafka\RdKafkaContext;
use Enqueue\RdKafka\RdKafkaProducer;
use Enqueue\RdKafka\RdKafkaTopic;
use Interop\Queue\Exception;
use Interop\Queue\Exception\InvalidDestinationException;
use Interop\Queue\Exception\InvalidMessageException;
use Rossel\RosselKafka\Model\MessageInterface;
use Rossel\RosselKafka\Model\Topic;

final class KafkaConnector implements KafkaConnectorInterface
{
    private readonly RdKafkaContext $rdKafkaContext;

    private readonly RdKafkaProducer $rdKafkaProducer;

    /** @var array<string, RdKafkaTopic> */
    private array $rdKafkaTopics = [];

    public function __construct(
        string $brokerUrl,
        private string $appName,
    ) {
        $this->rdKafkaContext = $this->buildContext($brokerUrl);
        $this->rdKafkaProducer = $this->rdKafkaContext->createProducer();
    }

    /**
     * Send a message to a Kafka topic.
     *
     * @throws InvalidDestinationException
     * @throws InvalidMessageException
     * @throws Exception
     */
    public function send(Topic|RdKafkaTopic $topic, MessageInterface $message): void
    {
        if ($topic instanceof Topic) {
            $topic = $this->getRdKafkaTopic($topic);
        }

        $this->rdKafkaProducer->send(
            $topic,
            $message->getRdKafkaMessage(),
        );
    }

    /**
     * Create a consumer object, which is responsible for listening messages published on a topic.
     */
    public function createConsumer(Topic $topic): RdKafkaConsumer
    {
        return $this->rdKafkaContext->createConsumer($this->getRdKafkaTopic($topic));
    }

    private function getRdKafkaTopic(Topic $topic): RdKafkaTopic
    {
        return $this->rdKafkaTopics[$topic->getName()] ??= $this->rdKafkaContext->createTopic($topic->getName());
    }

    private function buildContext(string $brokerUrl): RdKafkaContext
    {
        return $this->buildConnectionFactory($brokerUrl)->createContext();
    }

    private function buildConnectionFactory(string $brokerUrl): RdKafkaConnectionFactory
    {
        $brokerUrl = str_replace('kafka://', '', $brokerUrl);

        return new RdKafkaConnectionFactory([
            'global' => [
                'group.id' => $this->appName,
                'metadata.broker.list' => $brokerUrl,
                'enable.auto.commit' => 'true',
                'auto.commit.interval.ms' => '5s',
                'enable.idempotence' => 'true',
                'retries' => '2147483647',
                'linger.ms' => '100',
                'batch.size' => '16384',
                'fetch.min.bytes' => '1000',
            ],
            'topic' => [
                'auto.offset.reset' => 'latest',
                'request.required.acks' => 'all',
                'delivery.timeout.ms' => '518400000', // 6 days
                'compression.type' => 'gzip',
            ],
        ]);
    }
}
