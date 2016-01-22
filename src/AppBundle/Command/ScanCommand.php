<?php

namespace AppBundle\Command;


use AppBundle\Entity\Download;
use AppBundle\Entity\Notification;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\LockHandler;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\ProcessBuilder;

class ScanCommand extends ContainerAwareCommand
{
    private $pathes = [];

    protected function configure()
    {
        $this
            ->setName('app:scan')
            ->setDescription('Scan a url and referer for downloads, or process a number of urls from the queue')
            ->addOption('count', 'c', InputOption::VALUE_OPTIONAL, 'Number of entries to process (will be ignored if url is set)', 5)
            ->addArgument('url', null, 'url to scan', false)
            ->addArgument('referer', null, 'referer to include in scan', false);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $lock = new LockHandler($this->getName());
        if (!$lock->lock()) {
            $output->writeln($this->getName() . ' already in progress');
            return -1;
        }

        $em = $this->getContainer()->get('doctrine.orm.entity_manager');
        $url = $input->getArgument('url');
        if ($url) {
            $referer = $input->getArgument('referer');
            if (!$referer) {
                $referer = $url;
            }
            $downloads = $this->scan($url, $referer, $output);
            if ($downloads) {
                $results = $this->saveDownloads($downloads);
                $this->successNotification($results, $url, $referer);
            } else {
                $this->failureNotification($url, $referer);
            }
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
                $downloads = $this->scan($url, $referer);
                if ($downloads) {
                    $results = $this->saveDownloads($downloads);
                    $this->successNotification($results, $url, $referer);
                } else {
                    $this->failureNotification($url, $referer);
                }
                $em->remove($queuedUrl);
            }
        }
        $em->flush();

        $lock->release();
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
            'PHANTOMJS_EXECUTABLE' => $this->getPath('phantomjs_path', "{$appRoot}/scanner/node_modules/casperjs/node_modules/.bin/phantomjs"),
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
            ->add("--engine={$engine}")
            ->add("{$appRoot}/scanner/scanner.js")
            ->add($url)
            ->add($referer);

        $process = $processBuilder->getProcess();
        $logger->debug($process->getCommandLine());
        $process->run();


        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $output = explode("\n", $process->getOutput());
        $output = array_filter(
            $output,
            function ($line) {
                return $line != 'Vector smash protection is enabled.';
            }
        );

        $stderr = $process->getErrorOutput();
        if ($stderr) {
            foreach (explode("\n", $stderr) as $line) {
                $logger->error($line);
            }
        }

        return json_decode(implode("\n", $output), JSON_OBJECT_AS_ARRAY);
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

    private function saveDownloads(array $downloads)
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
            $existQuery = $em->getRepository('AppBundle:Download')
                             ->createQueryBuilder('d')
                             ->select('d.id')
                             ->where('d.checksum = :checksum')
                             ->getQuery();
            $existQuery->execute(['checksum' => $downloadEntity->getChecksum()]);
            if (count($existQuery->getArrayResult()) == 0) {
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

    private function successNotification($results, $url, $referer)
    {
        $notification = new Notification();
        $notification
            ->setType('info')
            ->setTitle('Scan successful')
            ->setText("{$results['saved']} saved, {$results['skipped']} skipped")
            ->setUrl($url)
            ->setReferer($referer);
        $this->getContainer()->get('doctrine.orm.entity_manager')->persist($notification);
        $this->getContainer()->get('logger')->info($notification->getText());
    }

    private function failureNotification($url, $referer)
    {
        $notification = new Notification();
        $notification
            ->setType('error')
            ->setTitle('Scan failed')
            ->setText("Failed to scan {$url}")
            ->setUrl($url)
            ->setReferer($referer);
        $this->getContainer()->get('doctrine.orm.entity_manager')->persist($notification);
        $this->getContainer()->get('logger')->error($notification->getText() . " (Referer: {$referer})");
    }


}
