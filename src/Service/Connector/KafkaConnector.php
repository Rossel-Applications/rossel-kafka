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
use Rossel\RosselKafka\Enum\Infrastructure\KafkaTopic;
use Rossel\RosselKafka\Model\MessageInterface;

final class KafkaConnector implements KafkaConnectorInterface
{
    private readonly RdKafkaContext $rdKafkaContext;

    private readonly RdKafkaProducer $rdKafkaProducer;

    /** @var \SplObjectStorage<KafkaTopic, RdKafkaTopic> */
    private \SplObjectStorage $topics;

    public function __construct(
        string $brokerUrl,
        private string $appName,
    ) {
        $this->topics = new \SplObjectStorage();
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
    public function send(KafkaTopic|RdKafkaTopic $topic, MessageInterface $message): void
    {
        if ($topic instanceof KafkaTopic) {
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
    public function createConsumer(KafkaTopic $kafkaTopic): RdKafkaConsumer
    {
        $rdKafkaTopic = $this->getRdKafkaTopic($kafkaTopic);

        return $this->rdKafkaContext->createConsumer($rdKafkaTopic);
    }

    /**
     * Get the RdKafkaTopic associated with a KafkaTopic enum.
     *
     * @throws \InvalidArgumentException if the topic is not registered
     */
    private function getRdKafkaTopic(KafkaTopic $topic): RdKafkaTopic
    {
        if (!$this->topics->contains($topic)) {
            throw new \InvalidArgumentException(\sprintf('Topic "%s" is not registered in the KafkaConnector.', $topic->name));
        }

        return $this->topics[$topic];
    }

    /**
     * Build the RdKafkaContext and initialize topics.
     */
    private function buildContext(string $brokerUrl): RdKafkaContext
    {
        $context = $this->buildConnectionFactory($brokerUrl)->createContext();

        foreach (KafkaTopic::cases() as $topic) {
            if (!$this->topics->contains($topic)) {
                $this->topics[$topic] = $context->createTopic($topic->name);
            }
        }

        return $context;
    }

    /**
     * Build the RdKafkaConnectionFactory with the given broker url.
     */
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
