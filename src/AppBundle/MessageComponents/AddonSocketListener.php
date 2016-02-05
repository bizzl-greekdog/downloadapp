<?php
/**
 * Created by PhpStorm.
 * User: benjamin
 * Date: 2/2/16
 * Time: 4:56 PM
 */

namespace AppBundle\MessageComponents;


use AppBundle\Command\ScanCommand;
use AppBundle\Entity\Notification;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

class AddonSocketListener implements MessageComponentInterface, ContainerAwareInterface
{
    use ContainerAwareTrait;

    /** @var  \SplObjectStorage */
    protected $clients;

    /** @var  OutputInterface */
    protected $output;

    /**
     * AddonSocketListener constructor.
     */
    public function __construct(OutputInterface $output)
    {
        $this->clients = new \SplObjectStorage();
        $this->output = $output;
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
        $this->output->writeln("New connection! ({$conn->resourceId})");
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
        $this->output->writeln("Connection {$conn->resourceId} has disconnected");
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
        $this->output->writeln("An error has occurred: {$e->getMessage()}");
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
        $this->output->writeln($msg);
        $msg = json_decode($msg);
        $input = new ArrayInput(['url' => $msg->url, 'referer' => $msg->referer]);
        $scanCommand = new ScanCommand();
        $scanCommand->setContainer($this->container);
        $returnValue = $scanCommand
            ->setNotificationCallback(
                function (Notification $notification) use ($from) {
                    $from->send(json_encode($notification));
                }
            )
            ->run($input, $this->output);
        $this->output->writeln("Scanner returned $returnValue");
    }
}
