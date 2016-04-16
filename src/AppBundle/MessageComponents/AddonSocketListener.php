<?php
/**
 * Created by PhpStorm.
 * User: benjamin
 * Date: 2/2/16
 * Time: 4:56 PM
 */

namespace AppBundle\MessageComponents;


use AppBundle\Utilities\ProcessBuffer;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use Ratchet\Server\IoServer;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\Process\ProcessBuilder;

class AddonSocketListener implements MessageComponentInterface, ContainerAwareInterface
{
    use ContainerAwareTrait;

    /** @var  \SplObjectStorage */
    protected $clients;

    /** @var  OutputInterface */
    protected $output;

    /** @var  IoServer */
    protected $server;
    
    /** @var bool  */
    private $silent = false;

    /**
     * AddonSocketListener constructor.
     * @param OutputInterface $output
     * @param bool $silent
     */
    public function __construct(OutputInterface $output, $silent = false)
    {
        $this->clients = new \SplObjectStorage();
        $this->output = $output;
        $this->silent = $silent;
    }
    
    private function writeln($msg) {
        if (!$this->silent) {
            $this->output->writeln($msg);
        }
    }

    /**
     * When a new connection is opened it will be passed to this method
     * @param  ConnectionInterface $conn The socket/connection that just connected to your application
     * @throws \Exception
     */
    function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        $this->container->get('logger')->info("New connection! ({$conn->resourceId})");
        $this->writeln("New connection! ({$conn->resourceId})");
    }

    /**
     * This is called before or after a socket is closed (depends on how it's closed).  SendMessage to $conn will not result in an error if it has already been closed.
     * @param  ConnectionInterface $conn The socket/connection that is closing/closed
     * @throws \Exception
     */
    function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
        $this->container->get('logger')->info("Connection {$conn->resourceId} has disconnected");
        $this->writeln("Connection {$conn->resourceId} has disconnected");
    }

    /**
     * If there is an error with one of the sockets, or somewhere in the application where an Exception is thrown,
     * the Exception is sent back down the stack, handled by the Server and bubbled back up the application through this method
     * @param  ConnectionInterface $conn
     * @param  \Exception $e
     * @throws \Exception
     */
    function onError(ConnectionInterface $conn, \Exception $e)
    {
        $this->container->get('logger')->error("An error has occurred: {$e->getMessage()}");
        $this->writeln("An error has occurred: {$e->getMessage()}");
        $this->clients->detach($conn);
        $conn->close();
    }

    /**
     * Triggered when a client sends data through the socket
     * @param  \Ratchet\ConnectionInterface $from The socket/connection that sent the message to your application
     * @param  string $msg The message received
     * @throws \Exception
     */
    function onMessage(ConnectionInterface $from, $msg)
    {
        $processBuilder = new ProcessBuilder();

        $processBuilder
            ->setTimeout(null)
            ->add(PHP_BINARY)
            ->add($this->container->get('kernel')->getRootDir() . '/console')
            ->add('app:scan')
            ->add('--ipc');

        $this->writeln($msg);
        $msg = json_decode($msg);
        if ($msg->url == 'watchlists') {
            $processBuilder->add('--watchlists');
        } else {
            $processBuilder
                ->add($msg->url)
                ->add($msg->referer);
        }

        $buffer = new ProcessBuffer(
            function ($line) use ($from) {
                if (substr($line, 0, 12) == 'NOTIFICATION') {
                    $from->send(substr($line, 13));
                }
                $this->getServer()->loop->tick();
            }
        );

        $process = $processBuilder->getProcess();

        $process->run($buffer->getProcessCallback());
        $buffer->close();

    }

    /**
     * @return IoServer
     */
    public function getServer()
    {
        return $this->server;
    }

    /**
     * @param IoServer $server
     * @return $this
     */
    public function setServer($server)
    {
        $this->server = $server;
        return $this;
    }
}
