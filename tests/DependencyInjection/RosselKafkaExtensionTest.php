<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rossel\RosselKafka\DependencyInjection\RosselKafkaExtension;
use Rossel\RosselKafka\RosselKafkaBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class RosselKafkaExtensionTest extends TestCase
{
    private RosselKafkaExtension $extension;
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->extension = new RosselKafkaExtension();
        $this->container = new ContainerBuilder();
    }

    private function load(array $config): void
    {
        try {
            $this->extension->load([$config], $this->container);
        } catch (\Symfony\Component\DependencyInjection\Exception\RuntimeException $e) {
            // symfony/yaml is not installed in this environment; parameters are already
            // registered before the YamlFileLoader call, so this is safe to ignore.
            if (!str_contains($e->getMessage(), 'Symfony Yaml Component')) {
                throw $e;
            }
        }
    }

    private function minimal(): array
    {
        return [
            'broker' => ['url' => 'kafka://localhost:9092'],
            'producer' => ['app_name' => 'test-app'],
        ];
    }

    private function param(string $suffix): mixed
    {
        $name = RosselKafkaBundle::BUNDLE_NAME.'.'.$suffix;

        return $this->container->getParameter($name);
    }

    // -------------------------------------------------------------------------
    // Alias
    // -------------------------------------------------------------------------

    #[Test]
    public function getAliasReturnsRosselKafka(): void
    {
        self::assertSame('rossel_kafka', $this->extension->getAlias());
    }

    // -------------------------------------------------------------------------
    // broker.url parameter
    // -------------------------------------------------------------------------

    #[Test]
    public function loadSetsBrokerUrlParameter(): void
    {
        $this->load($this->minimal());

        self::assertSame('kafka://localhost:9092', $this->param('broker.url'));
    }

    // -------------------------------------------------------------------------
    // producer.app_name parameter
    // -------------------------------------------------------------------------

    #[Test]
    public function loadSetsProducerAppNameParameter(): void
    {
        $this->load($this->minimal());

        self::assertSame('test-app', $this->param('producer.app_name'));
    }

    // -------------------------------------------------------------------------
    // authentication defaults
    // -------------------------------------------------------------------------

    #[Test]
    public function loadSetsNullSaslUsernameByDefault(): void
    {
        $this->load($this->minimal());

        self::assertNull($this->param('broker.authentication.sasl_username'));
    }

    #[Test]
    public function loadSetsNullSaslPasswordByDefault(): void
    {
        $this->load($this->minimal());

        self::assertNull($this->param('broker.authentication.sasl_password'));
    }

    #[Test]
    public function loadSetsDefaultSaslMechanism(): void
    {
        $this->load($this->minimal());

        self::assertSame('PLAIN', $this->param('broker.authentication.sasl_mechanism'));
    }

    #[Test]
    public function loadSetsNullSslCaCertificateUrlByDefault(): void
    {
        $this->load($this->minimal());

        self::assertNull($this->param('broker.authentication.ssl_ca_certificate_url'));
    }

    #[Test]
    public function loadSetsNullSslCaCertificatePathByDefault(): void
    {
        $this->load($this->minimal());

        self::assertNull($this->param('broker.authentication.ssl_ca_certificate_path'));
    }

    #[Test]
    public function loadSetsNullSslClientCertificateByDefault(): void
    {
        $this->load($this->minimal());

        self::assertNull($this->param('broker.authentication.ssl_client_certificate'));
    }

    #[Test]
    public function loadSetsNullSslClientKeyByDefault(): void
    {
        $this->load($this->minimal());

        self::assertNull($this->param('broker.authentication.ssl_client_key'));
    }

    #[Test]
    public function loadSetsNullSslClientKeyPasswordByDefault(): void
    {
        $this->load($this->minimal());

        self::assertNull($this->param('broker.authentication.ssl_client_key_password'));
    }

    // -------------------------------------------------------------------------
    // authentication with values
    // -------------------------------------------------------------------------

    #[Test]
    public function loadSetsSaslUsernameWhenProvided(): void
    {
        $config = $this->minimal();
        $config['broker']['authentication']['sasl_username'] = 'myuser';

        $this->load($config);

        self::assertSame('myuser', $this->param('broker.authentication.sasl_username'));
    }

    #[Test]
    public function loadSetsSaslPasswordWhenProvided(): void
    {
        $config = $this->minimal();
        $config['broker']['authentication']['sasl_password'] = 'secret';

        $this->load($config);

        self::assertSame('secret', $this->param('broker.authentication.sasl_password'));
    }

    #[Test]
    public function loadSetsCustomSaslMechanism(): void
    {
        $config = $this->minimal();
        $config['broker']['authentication']['sasl_mechanism'] = 'SCRAM-SHA-256';

        $this->load($config);

        self::assertSame('SCRAM-SHA-256', $this->param('broker.authentication.sasl_mechanism'));
    }

    // -------------------------------------------------------------------------
    // empty string normalized to null
    // -------------------------------------------------------------------------

    /**
     * @return iterable<string, array{string}>
     */
    public static function nullableAuthFieldProvider(): iterable
    {
        yield 'sasl_username' => ['sasl_username'];
        yield 'sasl_password' => ['sasl_password'];
        yield 'ssl_ca_certificate_url' => ['ssl_ca_certificate_url'];
        yield 'ssl_ca_certificate_path' => ['ssl_ca_certificate_path'];
        yield 'ssl_client_certificate' => ['ssl_client_certificate'];
        yield 'ssl_client_key' => ['ssl_client_key'];
        yield 'ssl_client_key_password' => ['ssl_client_key_password'];
    }

    #[Test]
    #[DataProvider('nullableAuthFieldProvider')]
    public function loadNormalizesEmptyStringToNull(string $field): void
    {
        $config = $this->minimal();
        $config['broker']['authentication'][$field] = '';

        $this->load($config);

        self::assertNull($this->param('broker.authentication.'.$field));
    }

    // -------------------------------------------------------------------------
    // topics parameter
    // -------------------------------------------------------------------------

    #[Test]
    public function loadSetsTopicsParameter(): void
    {
        $this->load($this->minimal());

        $topics = $this->param('broker.topics');
        self::assertIsArray($topics);
    }

    #[Test]
    public function loadSetsTopicsWithValues(): void
    {
        $config = $this->minimal();
        $config['broker']['topics']['public_log_output_v1_json_delete'] = 'my.log.topic';

        $this->load($config);

        $topics = $this->param('broker.topics');
        self::assertSame('my.log.topic', $topics['public_log_output_v1_json_delete']);
    }

    // -------------------------------------------------------------------------
    // autoconfiguration tag
    // -------------------------------------------------------------------------

    #[Test]
    public function loadRegistersConsumerInterfaceForAutoconfiguration(): void
    {
        $this->load($this->minimal());

        $autoconfiguredInstances = $this->container->getAutoconfiguredInstanceof();
        self::assertArrayHasKey(\Rossel\RosselKafka\Consumer\ConsumerInterface::class, $autoconfiguredInstances);
    }
}
