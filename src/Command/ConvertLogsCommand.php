<?php
/**
 * This file is part of the browscap-helper package.
 *
 * Copyright (c) 2015-2017, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);
namespace BrowscapHelper\Command;

use BrowscapHelper\Source\LogFileSource;
use Monolog\Handler\PsrHandler;
use Monolog\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ConvertLogsCommand
 *
 * @category   Browscap Helper
 *
 * @author     Thomas Müller <mimmi20@live.de>
 */
class ConvertLogsCommand extends Command
{
    /**
     * @var string
     */
    private $sourcesDirectory = '';

    /**
     * @var string
     */
    private $targetDirectory = '';

    /**
     * @var \Monolog\Logger
     */
    private $logger = null;

    /**
     * @param \Monolog\Logger $logger
     * @param string          $sourcesDirectory
     * @param string          $targetDirectory
     */
    public function __construct(Logger $logger, $sourcesDirectory, $targetDirectory)
    {
        $this->sourcesDirectory = $sourcesDirectory;
        $this->targetDirectory  = $targetDirectory;
        $this->logger           = $logger;

        parent::__construct();
    }

    /**
     * Configures the current command.
     */
    protected function configure()
    {
        $this
            ->setName('convert-logs')
            ->setDescription('Reads the server logs, extracts the useragents and writes them into a file')
            ->addOption(
                'resources',
                null,
                InputOption::VALUE_REQUIRED,
                'Where the resource files are located',
                $this->sourcesDirectory
            )
            ->addOption(
                'target',
                null,
                InputOption::VALUE_REQUIRED,
                'Where the target files should be written',
                $this->targetDirectory
            );
    }

    /**
     * Executes the current command.
     *
     * This method is not abstract because you can use this class
     * as a concrete class. In this case, instead of defining the
     * execute() method, you set the code to execute by passing
     * a Closure to the setCode() method.
     *
     * @param InputInterface  $input  An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     *
     * @throws \LogicException When this abstract method is not implemented
     *
     * @return null|int null or 0 if everything went fine, or an error code
     *
     * @see    setCode()
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $consoleLogger = new ConsoleLogger($output);
        $this->logger->pushHandler(new PsrHandler($consoleLogger));

        $targetDirectory  = $input->getOption('target');
        $sourcesDirectory = $input->getOption('resources');

        $counter        = 0;
        $targetBulkFile = $targetDirectory . date('Y-m-d') . '-testagents.txt';

        $output->writeln("reading from directory '" . $sourcesDirectory . "'");
        $output->writeln("writing to file '" . $targetBulkFile . "'");

        foreach ((new LogFileSource($this->logger, $output, $sourcesDirectory))->getUserAgents() as $agent) {
            file_put_contents($targetBulkFile, $agent . "\n", FILE_APPEND | LOCK_EX);
            ++$counter;
        }

        $output->writeln('');
        $output->writeln('finished reading files.');
        $output->writeln('');
        $output->writeln($counter . ' new  agents added');
    }
}
