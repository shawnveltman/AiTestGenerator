<?php

namespace Shawnveltman\AiTestGenerator\Commands;

use Illuminate\Console\Command;

class AiTestGeneratorCommand extends Command
{
    public $signature = 'aitestgenerator';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
