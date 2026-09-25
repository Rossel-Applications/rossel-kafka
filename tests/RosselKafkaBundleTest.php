<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rossel\RosselKafka\DependencyInjection\RosselKafkaExtension;
use Rossel\RosselKafka\RosselKafkaBundle;

final class RosselKafkaBundleTest extends TestCase
{
    private RosselKafkaBundle $bundle;

    protected function setUp(): void
    {
        $this->bundle = new RosselKafkaBundle();
    }

    #[Test]
    public function bundleNameConstantIsCorrect(): void
    {
        self::assertSame('rossel_kafka', RosselKafkaBundle::BUNDLE_NAME);
    }

    #[Test]
    public function getContainerExtensionReturnsRosselKafkaExtension(): void
    {
        $extension = $this->bundle->getContainerExtension();

        self::assertInstanceOf(RosselKafkaExtension::class, $extension);
    }

    #[Test]
    public function getContainerExtensionReturnsNewInstanceEachTime(): void
    {
        $extension1 = $this->bundle->getContainerExtension();
        $extension2 = $this->bundle->getContainerExtension();

        self::assertNotSame($extension1, $extension2);
    }
}
