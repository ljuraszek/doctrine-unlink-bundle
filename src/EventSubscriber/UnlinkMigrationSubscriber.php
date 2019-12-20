<?php

namespace LJuraszek\DoctrineUnlinkBundle\EventSubscriber;

use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Filesystem\Filesystem;

final class UnlinkMigrationSubscriber implements EventSubscriberInterface
{
    private const COMMAND_NAME = 'doctrine:migrations:execute';
    
    private $container;
    private $filesystem;
    
    public function __construct(ContainerInterface $container, Filesystem $filesystem)
    {
        $this->container  = $container;
        $this->filesystem = $filesystem;
    }
    
    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();
        
        if ($command === null || $command->getName() !== self::COMMAND_NAME) {
            return;
        }
    
        $input = $event->getInput();
        $command->addOption('no-unlink', null, InputOption::VALUE_NONE, 'Migration file will be removed after rollback');
        $input->bind($command->getDefinition());
    }
    
    public function onConsoleTerminate(ConsoleTerminateEvent $event): void
    {
        $command = $event->getCommand();
        
        if ($command === null || $event->getExitCode() !== 0 || $command->getName() !== self::COMMAND_NAME) {
            return;
        }
        
        $input  = $event->getInput();
        $unlink = $input->getOption('no-unlink') === false;
    
        if ($unlink && $input->getOption('down') === true) {
            $migrationVersion = $input->getArgument('version');
            $migrationsDir    = $this->container->getParameter('doctrine_migrations.dir_name');
            $migrationVersionPath = $migrationsDir.'/Version'.$migrationVersion.'.php';
            if ($this->filesystem->exists($migrationVersionPath)) {
                $this->filesystem->remove($migrationVersionPath);
                $io = new SymfonyStyle($input, $event->getOutput());
                $io->success(sprintf('File %s was successfully removed', $migrationVersionPath));
            }
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'console.command'   => 'onConsoleCommand',
            'console.terminate' => 'onConsoleTerminate',
        ];
    }
}
