<?php

declare(strict_types=1);

namespace Rossel\RosselKafkaPhpKit\Serializer;

use Rossel\RosselKafkaPhpKit\Dto\KafkaConfig;
use Rossel\RosselKafkaPhpKit\Dto\KafkaConfigInterface;
use Symfony\Component\Yaml\Yaml;

final class YamlConfigurationDeserializer
{
    public function parseConfigurationFile(string $filePath): KafkaConfigInterface
    {
        $data = Yaml::parseFile($filePath);

        return new KafkaConfig(
            address: $data['rossel_kafka']['address'],
            port: (int) $data['rossel_kafka']['port']
        );
    }
}

// todo reprendre la meme chose côté mwu ?
