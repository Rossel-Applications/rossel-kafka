<?php

use PHPUnit\Framework\TestCase;
use Rossel\RosselKafka\DependencyInjection\RosselKafkaExtension;
use Rossel\RosselKafka\RosselKafkaBundle;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

final class RosselKafkaBundleTest extends TestCase
{
    /**
     * Test that the bundle returns a valid container extension
     * of type RosselKafkaExtension.
     */
    public function testGetContainerExtensionReturnsRosselKafkaExtension(): void
    {
        $bundle = new RosselKafkaBundle();
        $extension = $bundle->getContainerExtension();

        self::assertInstanceOf(ExtensionInterface::class, $extension);
        self::assertInstanceOf(RosselKafkaExtension::class, $extension);
    }

    /**
     * Test that the bundle name constant is correctly defined.
     */
    public function testBundleNameConstant(): void
    {
        self::assertSame('rossel_kafka', RosselKafkaBundle::BUNDLE_NAME);
    }
}
