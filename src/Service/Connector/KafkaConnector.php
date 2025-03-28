<?php

declare(strict_types=1);

namespace Rossel\RosselKafkaPhpKit\Service\Connector;

use Enqueue\RdKafka\RdKafkaConnectionFactory;
use Enqueue\RdKafka\RdKafkaContext;
use Enqueue\RdKafka\RdKafkaProducer;
use Enqueue\RdKafka\RdKafkaTopic;
use Interop\Queue\Exception;
use Interop\Queue\Exception\InvalidDestinationException;
use Interop\Queue\Exception\InvalidMessageException;
use Rossel\RosselKafkaPhpKit\Dto\KafkaConfigInterface;
use Rossel\RosselKafkaPhpKit\Enum\Infrastructure\KafkaTopic;
use Rossel\RosselKafkaPhpKit\Model\MessageInterface;
use Rossel\RosselKafkaPhpKit\Serializer\YamlConfigurationDeserializer;

final class KafkaConnector implements KafkaConnectorInterface
{
    private readonly RdKafkaContext $rdKafkaContext;

    private readonly RdKafkaProducer $rdKafkaProducer;

    /** @var \SplObjectStorage<KafkaTopic, RdKafkaTopic> */
    private \SplObjectStorage $topics;

    private KafkaConfigInterface $config;

    public function __construct(
        private readonly YamlConfigurationDeserializer $yamlDeserializer,
        string $kafkaConfigFilePath
    ) {
        $this->config = $this->yamlDeserializer->parseConfigurationFile($kafkaConfigFilePath);
        $this->topics = new \SplObjectStorage();
        $this->rdKafkaContext = $this->buildContext(
            $this->config->getAddress(),
            $this->config->getPort()
        );
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
    private function buildContext(string $host, int $port): RdKafkaContext
    {
        $context = $this->buildConnectionFactory($host, $port)->createContext();

        foreach (KafkaTopic::cases() as $topic) {
            if (!$this->topics->contains($topic)) {
                $this->topics[$topic] = $context->createTopic($topic->name);
            }
        }

        return $context;
    }

    /**
     * Build the RdKafkaConnectionFactory with the given host and port.
     */
    private function buildConnectionFactory(string $host, int $port): RdKafkaConnectionFactory
    {
        return new RdKafkaConnectionFactory([
            'global' => [
                'group.id' => uniqid('', true),
                'metadata.broker.list' => \sprintf('%s:%s', $host, $port),
                'enable.auto.commit' => 'false',
            ],
            'topic' => [
                'auto.offset.reset' => 'beginning',
            ],
        ]);
    }
}
