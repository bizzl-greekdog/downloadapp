<?php

namespace AppBundle\Command;


use AppBundle\Entity\Download;
use AppBundle\Entity\Notification;
use AppBundle\Entity\QueuedUrl;
use AppBundle\Utilities\ProcessBuffer;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\ProcessBuilder;
use Symfony\Component\Yaml\Yaml;

class ScanCommand extends ContainerAwareCommand
{

    /** @var bool|OutputInterface */
    private $ipc = false;

    protected function configure()
    {
        $this
            ->setName('app:scan')
            ->setDescription('Scan a url and referer for downloads, or process a number of urls from the queue')
            ->addOption('count', 'c', InputOption::VALUE_OPTIONAL, 'Number of entries to process (will be ignored if url is set)', 5)
            ->addOption('watchlists', '', InputOption::VALUE_NONE, 'Get watchlists')
            ->addOption('ipc', '', InputOption::VALUE_NONE, 'Pass notifications via stdout')
            ->addOption('allow-dupes', '', InputOption::VALUE_NONE, 'Allow duplicate downloads')
            ->addArgument('url', null, 'url to scan', false)
            ->addArgument('referer', null, 'referer to include in scan', false);
    }

    private function sendNotification(Notification $notification)
    {
        if ($this->ipc) {
            $this->ipc->writeln('NOTIFICATION ' . json_encode($notification));
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $em = $this->getContainer()->get('doctrine.orm.entity_manager');
        $url = $input->getArgument('url');
        $this->ipc = $input->getOption('ipc') ? $output : false;
        $allowDupes = $input->getOption('allow-dupes');
        $notifications = [];
        $scanService = $this->getContainer()->get('app.scanner');
        if ($input->getOption('watchlists')) {
            $scanners = $this->getContainer()->get('kernel')->getRootDir() . '/config/scanners.yml';
            $scanners = Yaml::parse(file_get_contents($scanners));
            foreach ($scanners['scanners'] as $scanner) {
                if (!isset($scanner['watchlist']) || !$scanner['watchlist']) {
                    continue;
                }
                $notifications = $scanService->scan(
                    $scanner['watchlist']['key'],
                    $scanner['watchlist']['url'],
                    true,
                    $allowDupes,
                    $scanner['watchlist']['url'],
                    ''
                );
            }
        } elseif ($url) {
            $referer = $input->getArgument('referer');
            if (!$referer) {
                $referer = $url;
            }
            $notifications = $scanService->scan($url, $referer, false, $allowDupes);
        } else {
            $count = $input->getOption('count');
            $repo = $em->getRepository('AppBundle:QueuedUrl');
            $ids = $repo
                ->createQueryBuilder('q')
                ->select('q.id')
                ->addOrderBy('q.priority', 'DESC')
                ->addOrderBy('q.added', 'ASC')
                ->setMaxResults($count)
                ->getQuery()
                ->getResult();
            foreach ($ids as $id) {
                $queuedUrl = $repo->find($id['id']);
                $url = $queuedUrl->getUrl();
                $referer = $queuedUrl->getReferer();
                $notifications = array_merge($notifications, $scanService->scan($url, $referer, false, $allowDupes));
                $em->remove($queuedUrl);
            }
        }
        foreach ($notifications as $notification) {
            $this->sendNotification($notification);
        }
        $em->flush();

        return 0;
    }


}
