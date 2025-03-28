<?php

namespace Rossel\RosselKafkaPhpKit\Dto;

interface KafkaConfigInterface
{
    public function getAddress(): string;

    public function getPort(): int;
}