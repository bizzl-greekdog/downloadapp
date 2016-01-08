<?php

namespace AppBundle\Command;


use AppBundle\Entity\Download;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\LockHandler;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\ProcessBuilder;

class ScanCommand extends ContainerAwareCommand
{
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

        $url = $input->getArgument('url');
        if ($url) {
            $referer = $input->getArgument('referer');
            if (!$referer) {
                $referer = $url;
            }
            $downloads = $this->scan($url, $referer, $output);
            if ($downloads) {
                $results = $this->saveDownloads($downloads);
                $output->writeln("{$results['saved']} saved, {$results['skipped']} skipped");
            } else {
                $output->writeln("Failed to scan {$url} (Referer: {$referer})");
            }
        } else {
            $count = $input->getOption('count');
            $em = $this->getContainer()->get('doctrine.orm.entity_manager');
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
                $downloads = $this->scan($url, $referer, $output);
                if ($downloads) {
                    $results = $this->saveDownloads($downloads);
                    $output->writeln("{$results['saved']} saved, {$results['skipped']} skipped");
                } else {
                    $output->writeln("Failed to scan {$url} (Referer: {$referer})");
                }
                $em->remove($queuedUrl);
            }
            $em->flush();
        }

        $lock->release();
        return 0;
    }

    /**
     * @param string $url
     * @param string $referer
     * @param OutputInterface $output
     */
    private function scan($url, $referer, OutputInterface $output)
    {
        $engine = $this->getContainer()->getParameter('scanner_engine');
        $appRoot = $this->getContainer()->getParameter('kernel.root_dir') . '/..';

        $pathes = [
            'SLIMERJS_EXECUTABLE'  => "{$appRoot}/scanner/node_modules/.bin/slimerjs",
            'PHANTOMJS_EXECUTABLE' => "{$appRoot}/scanner/node_modules/casperjs/node_modules/.bin/phantomjs",
        ];

        $processBuilder = new ProcessBuilder();
        if ($engine == 'slimerjs') {
            //$processBuilder->add('xvfb-run');
        }
        $processBuilder
            ->addEnvironmentVariables($pathes)
            ->add("{$appRoot}/scanner/node_modules/.bin/casperjs")
            ->add("--engine={$engine}")
            ->add("{$appRoot}/scanner/scanner.js")
            ->add($url)
            ->add($referer);

        $process = $processBuilder->getProcess();
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

        return json_decode(implode("\n", $output), JSON_OBJECT_AS_ARRAY);
    }

    private function saveDownloads(array $downloads)
    {
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
            } else {
                $result['skipped']++;
            }
        }
        $em->flush();
        return $result;
    }


}
