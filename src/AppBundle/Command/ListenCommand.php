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
        $this->setName('app:listen')
             ->setDescription('Listen for websocket requests (blocks indefinitely)')
             ->addOption('port', 'p', InputOption::VALUE_REQUIRED, 'The port to listen on', 8888)
             ->addOption('interface', 'i', InputOption::VALUE_REQUIRED, 'The interface to listen on', '0.0.0.0');
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $addonSocketListener = new AddonSocketListener($output);
        $addonSocketListener->setContainer($this->getContainer());
        $server = IoServer::factory(new HttpServer(new WsServer($addonSocketListener)), $input->getOption('port'), $input->getOption('interface'));
        $addonSocketListener->setServer($server);
        $output->writeln(sprintf('Listening to %s:%s', $input->getOption('interface'), $input->getOption('port')));
        $server->run();
        return 0;
    }


}
