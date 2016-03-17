<?php

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class StatsCommand extends ContainerAwareCommand
{

    /**
     * Setup routine.
     */
    protected function configure()
    {
        $this
            ->setName('app:stats')
            ->setDescription('Get some stats');
    }

    /**
     * Main routine.
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $table = new Table($output);
        $stats = $this->getContainer()->get('app.stats')->getStats();
        foreach ($stats as $stat => $statValue) {
            $table->addRow([$stat, $statValue]);
        }
        $table->render();
    }
}
