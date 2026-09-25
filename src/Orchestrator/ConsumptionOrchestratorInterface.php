<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Orchestrator;

use Rossel\RosselKafka\Model\Topic;

interface ConsumptionOrchestratorInterface
{
    public function listen(
        Topic $topic,
        ?\Closure $onStartCallable = null,
        ?\Closure $onIdleCallable = null,
        ?\Closure $onMessageCallable = null,
    ): void;
}
