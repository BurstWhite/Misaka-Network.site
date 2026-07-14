<?php

namespace Tests\Unit;

use Laravel\Horizon\Console\WorkCommand;
use Tests\TestCase;

class HorizonWorkCommandTest extends TestCase
{
    public function test_horizon_worker_accepts_the_framework_idle_timeout_option(): void
    {
        $command = $this->app->make(WorkCommand::class);

        $this->assertTrue($command->getDefinition()->hasOption('stop-when-empty-for'));
        $this->assertSame(0, $command->getDefinition()->getOption('stop-when-empty-for')->getDefault());
    }
}
