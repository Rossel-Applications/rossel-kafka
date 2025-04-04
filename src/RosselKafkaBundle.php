<?php

declare(strict_types=1);

namespace Rossel\RosselKafka;

use Rossel\RosselKafka\DependencyInjection\RosselKafkaExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class RosselKafkaBundle extends AbstractBundle
{
    public const BUNDLE_NAME = 'rossel_kafka';

    public function getContainerExtension(): ExtensionInterface
    {
        return new RosselKafkaExtension();
    }
}
