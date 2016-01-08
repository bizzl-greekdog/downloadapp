<?php
namespace AppBundle\Command;


use AppBundle\Entity\QueuedUrl;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class EnqueueCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:enqueue')
            ->setDescription('Add a url and referer to the scanner queue')
            ->addOption('priority', 'p', InputOption::VALUE_OPTIONAL, 'Priority', 10)
            ->addArgument('url', InputOption::VALUE_REQUIRED, 'url to enqueue')
            ->addArgument('referer', InputOption::VALUE_REQUIRED, 'referer to include in scan');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $queuedUrl = new QueuedUrl();
        $queuedUrl
            ->setUrl($input->getArgument('url'))
            ->setReferer($input->getArgument('referer'))
            ->setPriority($input->getOption('priority'));
        $em = $this->getContainer()->get('doctrine.orm.entity_manager');
        $em->persist($queuedUrl);
        $em->flush();
        return 0;
    }

}
