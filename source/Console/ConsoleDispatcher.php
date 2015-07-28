<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Console;

use Spiral\Application\DispatcherInterface;
use Spiral\Core\ContainerInterface;
use Spiral\Core\HippocampusInterface;
use Spiral\Core\Loader;
use Spiral\Core\Singleton;
use Spiral\Core\Traits\ConfigurableTrait;
use Spiral\Debug\Snapshot;
use Spiral\Tokenizer\TokenizerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

class ConsoleDispatcher extends Singleton implements DispatcherInterface
{
    /**
     * Configuring.
     */
    use ConfigurableTrait;

    /**
     * Declares to IoC that component instance should be treated as singleton.
     */
    const SINGLETON = self::class;

    /**
     * TokenizerInterface instance.
     *
     * @var TokenizerInterface
     */
    protected $tokenizer = null;

    /**
     * Runtime cache manager.
     *
     * @invisible
     * @var HippocampusInterface
     */
    protected $runtime = null;

    /**
     * Loader component.
     *
     * @var Loader
     */
    protected $loader = null;

    /**
     * Container.
     *
     * @invisible
     * @var ContainerInterface
     */
    protected $container = null;

    /**
     * Console application instance.
     *
     * @var ConsoleApplication
     */
    protected $application = null;

    /**
     * Cached list of all existed commands.
     *
     * @var array
     */
    protected $commands = [];

    /**
     * New instance of console dispatcher.
     *
     * @param HippocampusInterface $runtime
     * @param TokenizerInterface   $tokenizer
     * @param ContainerInterface   $container
     * @param Loader               $loader
     */
    public function __construct(
        HippocampusInterface $runtime,
        TokenizerInterface $tokenizer,
        ContainerInterface $container,
        Loader $loader
    )
    {
        $this->runtime = $runtime;
        $this->tokenizer = $tokenizer;
        $this->loader = $loader;
        $this->container = $container;

        $this->commands = $runtime->loadData('commands');

        if (!is_array($this->commands))
        {
            $this->commands = [];
        }
    }

    /**
     * Get or create consoleApplication instance.
     *
     * @return ConsoleApplication
     */
    public function application()
    {
        if (!empty($this->application))
        {
            return $this->application;
        }

        $this->application = new ConsoleApplication($this->container);

        //Commands lookup
        empty($this->commands) && $this->findCommands();

        foreach ($this->commands as $command)
        {
            try
            {
                $command = $this->container->get($command);
                if (method_exists($command, 'isAvailable') && !$command->isAvailable())
                {
                    continue;
                }
            }
            catch (\Exception $exception)
            {
                continue;
            }

            $this->application->add($command);
        }

        return $this->application;
    }

    /**
     * Use tokenizer to find all available command classes, result will be stored in runtime cache
     * to speed up next console call. Command can be called manually to reindex commands.
     */
    public function findCommands()
    {
        $this->commands = [];

        $classes = $this->tokenizer->getClasses(Command::class, null, 'Command');

        foreach ($classes as $class)
        {
            if ($class['abstract'])
            {
                continue;
            }

            $this->commands[] = $class['name'];
        }

        $this->runtime->saveData('commands', $this->commands);
    }

    /**
     * List of all available command classes.
     *
     * @return array
     */
    public function getCommands()
    {
        return $this->commands;
    }

    /**
     * Start dispatcher.
     */
    public function start()
    {
        $this->loader->setName('loadmap-console');

        //Console root directory is not equals to webroot
        chdir(dirname(directory('root')));

        $this->application()->run();
    }

    /**
     * Simplified method to perform one command using it's name.
     *
     * @param string               $command    Command name, for example "core:configure".
     * @param array|InputInterface $parameters Command parameters or input interface.
     * @param OutputInterface      $output     Output interface, buffer one will be used if nothing
     *                                         else specified.
     * @return CommandOutput
     * @throws \Exception
     */
    public function command($command, $parameters = [], OutputInterface $output = null)
    {
        $code = $this->application()->find($command)->run(
            is_object($parameters) ? $parameters : new ArrayInput(compact('command') + $parameters),
            $output = ($output ?: new BufferedOutput())
        );

        return new CommandOutput($code, $output);
    }

    /**
     * Every dispatcher should know how to handle exception snapshot provided by Debugger.
     *
     * @param Snapshot $snapshot
     * @return mixed
     */
    public function handleException(Snapshot $snapshot)
    {
        $this->application()->renderException($snapshot->getException(), new ConsoleOutput());
    }
}