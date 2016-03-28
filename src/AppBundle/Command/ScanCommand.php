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
    private $pathes = [];

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

    private function saveScan($scanned, $url, $referer, $autoOpen = false, $allowDupes = false)
    {
        if ($scanned['total']) {
            $results = $this->saveDownloads($scanned['downloads'], $allowDupes);
            $results['queued'] = $this->enqueueUrls($scanned['queued']);
            $this->successNotification($results, $url, $referer, $autoOpen);
        } else {
            $this->failureNotification($url, $referer, $autoOpen);
        }
    }

    private function enqueueUrls(array $urls)
    {
        $em = $this->getContainer()->get('doctrine.orm.entity_manager');
        $result = 0;
        foreach ($urls as $url) {
            $queuedUrl = new QueuedUrl();
            $queuedUrl
                ->setUrl($url)
                ->setReferer($url)
                ->setPriority(999);
            $em->persist($queuedUrl);
            $result++;
        }
        return $result;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $em = $this->getContainer()->get('doctrine.orm.entity_manager');
        $url = $input->getArgument('url');
        $this->ipc = $input->getOption('ipc') ? $output : false;
        $allowDupes = $input->getOption('allow-dupes');
        if ($input->getOption('watchlists')) {
            $scanners = $this->getContainer()->get('kernel')->getRootDir() . '/config/scanners.yml';
            $scanners = Yaml::parse(file_get_contents($scanners));
            foreach ($scanners['scanners'] as $scanner) {
                if (!isset($scanner['watchlist']) || !$scanner['watchlist']) {
                    continue;
                }
                $scanned = $this->scan($scanner['watchlist']['key'], $scanner['watchlist']['url']);
                $this->saveScan($scanned, $scanner['watchlist']['url'], '', true, $allowDupes);
            }
        } elseif ($url) {
            $referer = $input->getArgument('referer');
            if (!$referer) {
                $referer = $url;
            }
            $scanned = $this->scan($url, $referer);
            $this->saveScan($scanned, $url, $referer, false, $allowDupes);
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
                $scanned = $this->scan($url, $referer);
                $this->saveScan($scanned, $url, $referer, false, $allowDupes);
                $em->remove($queuedUrl);
            }
        }
        $em->flush();

        return 0;
    }

    /**
     * @param string $url
     * @param string $referer
     */
    private function scan($url, $referer)
    {
        $logger = $this->getContainer()->get('logger');
        $engine = $this->getContainer()->getParameter('scanner_engine');
        $appRoot = $this->getContainer()->getParameter('kernel.root_dir') . '/..';

        $paths = [
            'SLIMERJS_EXECUTABLE'  => $this->getPath('slimerjs_path', "{$appRoot}/scanner/node_modules/.bin/slimerjs"),
            'PHANTOMJS_EXECUTABLE' => $this->getPath('phantomjs_path', "{$appRoot}/scanner/node_modules/.bin/phantomjs"),
        ];
        foreach ($paths as $var => $path) {
            $logger->debug("{$var} => {$path}");
        }

        $processBuilder = new ProcessBuilder();
        if ($engine == 'slimerjs') {
            $processBuilder->add('xvfb-run');
        }
        $processBuilder
            ->addEnvironmentVariables($paths)
            ->add("{$appRoot}/scanner/node_modules/.bin/casperjs")
            ->add("--engine={$engine}");
        if ($cookies = $this->getContainer()->getParameter('cookies')) {
            $processBuilder->add("--cookies-file={$cookies}");
        }
        $processBuilder
            ->add("{$appRoot}/scanner/scanner.js")
            ->add($url)
            ->add($referer);

        $process = $processBuilder->getProcess();
        $process->setTimeout(null);
        $logger->debug($process->getCommandLine());

        $downloads = [];
        $queued = [];

        $buffer = new ProcessBuffer(
            function ($line) use (&$downloads, &$queued, $logger, $url, $referer) {
                if (substr($line, 0, 7) == 'ENQUEUE') {
                    $queued[] = substr($line, 8);
                } elseif (substr($line, 0, 8) == 'DOWNLOAD') {
                    $download = json_decode(substr($line, 9), JSON_OBJECT_AS_ARRAY);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $logger->error(json_last_error_msg());
                        $logger->error(substr($line, 9));
                    }
                    $downloads[] = $download;
                } elseif (substr($line, 0, 6) == 'NOTIFY') {
                    $n = new Notification();
                    $n
                        ->setType('info')
                        ->setTitle('Information')
                        ->setText(substr($line, 7))
                        ->setUrl($url)
                        ->setReferer($referer);
                    $this->sendNotification($n);
                } elseif (substr($line, 0, 5) == 'ALERT') {
                    $n = new Notification(true);
                    $n
                        ->setType('info')
                        ->setTitle('Information')
                        ->setText(substr($line, 6))
                        ->setUrl($url)
                        ->setReferer($referer);
                    $this->sendNotification($n);
                } else {
                    $logger->info($line);
                }
            },
            function ($line) use ($logger) {
                $logger->error($line);
            }
        );

        $process->run($buffer->getProcessCallback());

        $buffer->close();

        if (!$process->isSuccessful()) {
            //throw new ProcessFailedException($process);
            $logger->error('Scanner failed hard');
        }

        $stderr = $process->getErrorOutput();
        if ($stderr) {
            foreach (explode("\n", $stderr) as $line) {
                $logger->error($line);
            }
        }

        return ['downloads' => $downloads, 'queued' => $queued, 'total' => count($queued) + count($downloads)];
    }

    private function getPath($varname, $default)
    {
        if (!isset($this->pathes[$varname])) {
            try {
                $this->pathes[$varname] = $this->getContainer()->getParameter($varname);
            } catch (\Exception $e) {
                $this->pathes[$varname] = $default;
            }
        }
        return $this->pathes[$varname];
    }

    private function sendNotification(Notification $notification)
    {
        $this->getContainer()->get('doctrine.orm.entity_manager')->persist($notification);
        if ($this->ipc) {
            $this->getContainer()->get('doctrine.orm.entity_manager')->flush();
            $this->ipc->writeln('NOTIFICATION ' . json_encode($notification));
        }
    }

    private function saveDownloads(array $downloads, $allowDupes = false)
    {
        $logger = $this->getContainer()->get('logger');
        $em = $this->getContainer()->get('doctrine.orm.entity_manager');
        $result = ['saved' => 0, 'skipped' => 0];
        foreach ($downloads as $download) {
            $downloadEntity = new Download();
            $downloadEntity
                ->setUrl($download['url'])
                ->setFilename($download['filename'])
                ->setReferer($download['referer'])
                ->setComment($download['comment']);
            if (!$allowDupes) {
                $existQuery = $em->getRepository('AppBundle:Download')
                                 ->createQueryBuilder('d')
                                 ->select('d.id')
                                 ->where('d.checksum = :checksum')
                                 ->getQuery();
                $existQuery->execute(['checksum' => $downloadEntity->getChecksum()]);
                $isDuplicate = count($existQuery->getArrayResult()) > 0;
            } else {
                $isDuplicate = false;
            }
            if (!$isDuplicate) {
                foreach ($download['metadata'] as $title => $value) {
                    $downloadEntity->addMetadatum($title, $value);
                }
                $em->persist($downloadEntity);
                $result['saved']++;
                $logger->debug("{$download['url']} from {$download['referer']} saved");
            } else {
                $result['skipped']++;
                $logger->error("{$download['url']} from {$download['referer']} skipped");
            }
        }
        $em->flush();
        return $result;
    }

    private function successNotification($results, $url, $referer, $autoOpen = false)
    {
        $notification = new Notification($autoOpen);
        $notification
            ->setType('info')
            ->setTitle('Scan successful')
            ->setText("{$results['saved']} saved, {$results['skipped']} skipped, {$results['queued']} enqueued")
            ->setUrl($url)
            ->setReferer($referer);
        $this->sendNotification($notification);
        $this->getContainer()->get('logger')->info($notification->getText());
    }

    private function failureNotification($url, $referer, $autoOpen = false)
    {
        $notification = new Notification($autoOpen);
        $notification
            ->setType('error')
            ->setTitle('Scan failed')
            ->setText("Failed to scan {$url}")
            ->setUrl($url)
            ->setReferer($referer);
        $this->sendNotification($notification);
        $this->getContainer()->get('logger')->error($notification->getText() . " (Referer: {$referer})");
    }


}
