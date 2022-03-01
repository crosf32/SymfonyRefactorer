<?php

namespace Crosf32\ControllerRefactorer;

use Crosf32\ControllerRefactorer\Command\ControllerRefactorerCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class Refactorer extends Bundle
{
    public function registerCommands(Application $application)
    {
        $kernel = $application->getKernel();
        $application->add(new ControllerRefactorerCommand($kernel));
    }
}
