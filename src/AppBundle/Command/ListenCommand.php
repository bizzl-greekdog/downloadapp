<?php
/**
 * Created by PhpStorm.
 * User: benjamin
 * Date: 2/2/16
 * Time: 5:48 PM
 */

namespace AppBundle\Command;


use AppBundle\MessageComponents\AddonSocketListener;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ListenCommand extends ContainerAwareCommand
{
    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this
            ->setName('app:listen')
            ->setDescription('Listen for websocket requests (blocks indefinitely)')
            ->addOption('port', 'p', InputOption::VALUE_REQUIRED, 'The port to listen on', 8888)
            ->addOption('interface', 'i', InputOption::VALUE_REQUIRED, 'The interface to listen on', '0.0.0.0')
            ->addOption('quiet', 'q', InputOption::VALUE_NONE, 'Silent mode')
            ->addOption('pidfile', 'f', InputOption::VALUE_OPTIONAL, 'Write process id to file', '');
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $pidfile = $input->getOption('pidfile');
        $silent = $input->getOption('quiet');
        $port = $input->getOption('port');
        $address = $input->getOption('interface');

        $addonSocketListener = new AddonSocketListener($output, $silent);
        $addonSocketListener->setContainer($this->getContainer());
        $server = IoServer::factory(new HttpServer(new WsServer($addonSocketListener)), $port, $address);
        $addonSocketListener->setServer($server);
        if (!$silent) {
            $output->writeln(sprintf('Listening to %s:%s', $address, $port));
        }
        if ($pidfile) {
            file_put_contents($pidfile, getmypid());
        }
        $server->run();
        if ($pidfile) {
            unlink($pidfile);
        }
        return 0;
    }


}
