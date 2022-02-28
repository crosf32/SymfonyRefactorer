<?php

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:refactor-symfony',
    description: 'Refactor Symfony Controller to multiple classes.',
    hidden: false,
)]
class RefactorerCommand extends Command
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('namespace', InputArgument::REQUIRED, 'Controller Namespace');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $classNameSpace = $input->getArgument('namespace');
        include $classNameSpace;

        $refClass = new ReflectionClass($classNameSpace);

        $output->writeln($refClass->getMethods());

        return Command::SUCCESS;
    }
}
