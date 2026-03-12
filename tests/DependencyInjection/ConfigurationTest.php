<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rossel\RosselKafka\DependencyInjection\Configuration;
use Rossel\RosselKafka\Enum\Config\Broker\BrokerConfigKeys;
use Rossel\RosselKafka\Enum\Config\Broker\TopicConfigKeys;
use Rossel\RosselKafka\Enum\Config\Producer\ProducerConfigKeys;
use Rossel\RosselKafka\Enum\Config\RootConfigKeys;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    private Processor $processor;
    private Configuration $configuration;

    protected function setUp(): void
    {
        $this->processor = new Processor();
        $this->configuration = new Configuration();
    }

    // -------------------------------------------------------------------------
    // Minimal valid config
    // -------------------------------------------------------------------------

    #[Test]
    public function processesMinimalValidConfig(): void
    {
        $config = $this->process([
            RootConfigKeys::BROKER->value => [
                BrokerConfigKeys::URL->value => 'kafka://localhost:9092',
            ],
            RootConfigKeys::PRODUCER->value => [
                ProducerConfigKeys::APP_NAME->value => 'my-app',
            ],
        ]);

        /** @var array<string, mixed> $broker */
        $broker = $config[RootConfigKeys::BROKER->value];
        /** @var array<string, mixed> $producer */
        $producer = $config[RootConfigKeys::PRODUCER->value];

        self::assertSame('kafka://localhost:9092', $broker[BrokerConfigKeys::URL->value]);
        self::assertSame('my-app', $producer[ProducerConfigKeys::APP_NAME->value]);
    }

    // -------------------------------------------------------------------------
    // Required fields
    // -------------------------------------------------------------------------

    #[Test]
    public function throwsWhenBrokerUrlMissing(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process([
            RootConfigKeys::BROKER->value => [],
            RootConfigKeys::PRODUCER->value => [ProducerConfigKeys::APP_NAME->value => 'app'],
        ]);
    }

    #[Test]
    public function throwsWhenBrokerUrlIsEmpty(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process([
            RootConfigKeys::BROKER->value => [BrokerConfigKeys::URL->value => ''],
            RootConfigKeys::PRODUCER->value => [ProducerConfigKeys::APP_NAME->value => 'app'],
        ]);
    }

    #[Test]
    public function throwsWhenProducerAppNameMissing(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process([
            RootConfigKeys::BROKER->value => [BrokerConfigKeys::URL->value => 'kafka://host:9092'],
            RootConfigKeys::PRODUCER->value => [],
        ]);
    }

    #[Test]
    public function throwsWhenProducerAppNameIsEmpty(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process([
            RootConfigKeys::BROKER->value => [BrokerConfigKeys::URL->value => 'kafka://host:9092'],
            RootConfigKeys::PRODUCER->value => [ProducerConfigKeys::APP_NAME->value => ''],
        ]);
    }

    // -------------------------------------------------------------------------
    // Authentication defaults
    // -------------------------------------------------------------------------

    #[Test]
    public function authenticationDefaultsToNullsAndPlainMechanism(): void
    {
        $config = $this->processMinimal();

        /** @var array<string, mixed> $broker */
        $broker = $config[RootConfigKeys::BROKER->value];
        /** @var array<string, mixed> $auth */
        $auth = $broker[BrokerConfigKeys::AUTHENTICATION->value];

        self::assertNull($auth[BrokerConfigKeys::SASL_USERNAME->value]);
        self::assertNull($auth[BrokerConfigKeys::SASL_PASSWORD->value]);
        self::assertSame('PLAIN', $auth[BrokerConfigKeys::SASL_MECHANISM->value]);
        self::assertNull($auth[BrokerConfigKeys::SSL_CA_CERTIFICATE_URL->value]);
        self::assertNull($auth[BrokerConfigKeys::SSL_CA_CERTIFICATE_PATH->value]);
        self::assertNull($auth[BrokerConfigKeys::SSL_CLIENT_CERTIFICATE->value]);
        self::assertNull($auth[BrokerConfigKeys::SSL_CLIENT_KEY->value]);
        self::assertNull($auth[BrokerConfigKeys::SSL_CLIENT_KEY_PASSWORD->value]);
    }

    #[Test]
    public function authenticationValuesCanBeSet(): void
    {
        $config = $this->process([
            RootConfigKeys::BROKER->value => [
                BrokerConfigKeys::URL->value => 'kafka://host:9092',
                BrokerConfigKeys::AUTHENTICATION->value => [
                    BrokerConfigKeys::SASL_USERNAME->value => 'user',
                    BrokerConfigKeys::SASL_PASSWORD->value => 'pass',
                    BrokerConfigKeys::SASL_MECHANISM->value => 'SCRAM-SHA-256',
                    BrokerConfigKeys::SSL_CA_CERTIFICATE_URL->value => 'https://ca.example.com/cert.pem',
                    BrokerConfigKeys::SSL_CA_CERTIFICATE_PATH->value => '/etc/ssl/ca.pem',
                    BrokerConfigKeys::SSL_CLIENT_CERTIFICATE->value => '-----BEGIN CERTIFICATE-----',
                    BrokerConfigKeys::SSL_CLIENT_KEY->value => '-----BEGIN PRIVATE KEY-----',
                    BrokerConfigKeys::SSL_CLIENT_KEY_PASSWORD->value => 'keypass',
                ],
            ],
            RootConfigKeys::PRODUCER->value => [ProducerConfigKeys::APP_NAME->value => 'app'],
        ]);

        /** @var array<string, mixed> $broker */
        $broker = $config[RootConfigKeys::BROKER->value];
        /** @var array<string, mixed> $auth */
        $auth = $broker[BrokerConfigKeys::AUTHENTICATION->value];

        self::assertSame('user', $auth[BrokerConfigKeys::SASL_USERNAME->value]);
        self::assertSame('pass', $auth[BrokerConfigKeys::SASL_PASSWORD->value]);
        self::assertSame('SCRAM-SHA-256', $auth[BrokerConfigKeys::SASL_MECHANISM->value]);
        self::assertSame('https://ca.example.com/cert.pem', $auth[BrokerConfigKeys::SSL_CA_CERTIFICATE_URL->value]);
        self::assertSame('/etc/ssl/ca.pem', $auth[BrokerConfigKeys::SSL_CA_CERTIFICATE_PATH->value]);
        self::assertSame('keypass', $auth[BrokerConfigKeys::SSL_CLIENT_KEY_PASSWORD->value]);
    }

    // -------------------------------------------------------------------------
    // Topics defaults
    // -------------------------------------------------------------------------

    #[Test]
    public function allTopicsDefaultToNull(): void
    {
        $config = $this->processMinimal();

        /** @var array<string, mixed> $broker */
        $broker = $config[RootConfigKeys::BROKER->value];
        /** @var array<string, string|null> $topics */
        $topics = $broker[BrokerConfigKeys::TOPICS->value];

        foreach (TopicConfigKeys::cases() as $key) {
            self::assertArrayHasKey($key->value, $topics);
            self::assertNull($topics[$key->value], "Topic {$key->value} should default to null");
        }
    }

    #[Test]
    public function topicsCanBeSet(): void
    {
        $config = $this->process([
            RootConfigKeys::BROKER->value => [
                BrokerConfigKeys::URL->value => 'kafka://host:9092',
                BrokerConfigKeys::TOPICS->value => [
                    TopicConfigKeys::PUBLIC_LOG_OUTPUT_V1_JSON_DELETE->value => 'my.log.topic',
                    TopicConfigKeys::PUBLIC_SUBSCRIPTION_INPUT_V1_JSON_DELETE->value => 'my.sub.topic',
                ],
            ],
            RootConfigKeys::PRODUCER->value => [ProducerConfigKeys::APP_NAME->value => 'app'],
        ]);

        /** @var array<string, mixed> $broker */
        $broker = $config[RootConfigKeys::BROKER->value];
        /** @var array<string, string|null> $topics */
        $topics = $broker[BrokerConfigKeys::TOPICS->value];

        self::assertSame('my.log.topic', $topics[TopicConfigKeys::PUBLIC_LOG_OUTPUT_V1_JSON_DELETE->value]);
        self::assertSame('my.sub.topic', $topics[TopicConfigKeys::PUBLIC_SUBSCRIPTION_INPUT_V1_JSON_DELETE->value]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function saslMechanismProvider(): iterable
    {
        yield 'PLAIN' => ['PLAIN'];
        yield 'SCRAM-SHA-256' => ['SCRAM-SHA-256'];
        yield 'SCRAM-SHA-512' => ['SCRAM-SHA-512'];
    }

    #[Test]
    #[DataProvider('saslMechanismProvider')]
    public function saslMechanismAcceptsValidValues(string $mechanism): void
    {
        $config = $this->process([
            RootConfigKeys::BROKER->value => [
                BrokerConfigKeys::URL->value => 'kafka://host:9092',
                BrokerConfigKeys::AUTHENTICATION->value => [
                    BrokerConfigKeys::SASL_MECHANISM->value => $mechanism,
                ],
            ],
            RootConfigKeys::PRODUCER->value => [ProducerConfigKeys::APP_NAME->value => 'app'],
        ]);

        /** @var array<string, mixed> $broker */
        $broker = $config[RootConfigKeys::BROKER->value];
        /** @var array<string, mixed> $auth */
        $auth = $broker[BrokerConfigKeys::AUTHENTICATION->value];

        self::assertSame($mechanism, $auth[BrokerConfigKeys::SASL_MECHANISM->value]);
    }

    // -------------------------------------------------------------------------
    // getConfigTreeBuilder
    // -------------------------------------------------------------------------

    #[Test]
    public function getConfigTreeBuilderReturnsTreeBuilderInstance(): void
    {
        $treeBuilder = $this->configuration->getConfigTreeBuilder();

        self::assertInstanceOf(\Symfony\Component\Config\Definition\Builder\TreeBuilder::class, $treeBuilder);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function process(array $config): array
    {
        return $this->processor->processConfiguration($this->configuration, [$config]);
    }

    /**
     * @return array<string, mixed>
     */
    private function processMinimal(): array
    {
        return $this->process([
            RootConfigKeys::BROKER->value => [
                BrokerConfigKeys::URL->value => 'kafka://localhost:9092',
            ],
            RootConfigKeys::PRODUCER->value => [
                ProducerConfigKeys::APP_NAME->value => 'test-app',
            ],
        ]);
    }
}
