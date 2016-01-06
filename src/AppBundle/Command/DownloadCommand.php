<?php

namespace AppBundle\Command;

use Guzzle\Http\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\LockHandler;

/**
 * Console command to download a number of files listed in the downloads table.
 * @package AppBundle\Command
 */
class DownloadCommand extends Command implements ContainerAwareInterface
{
    /**
     * @var ContainerInterface
     */
    private $container = null;

    /**
     * Setup routine.
     */
    protected function configure()
    {
        $this
            ->setName('app:download')
            ->setDescription('Download a number of files')
            ->addArgument('number', null, 'Number of files to download', 5);
    }

    /**
     * Main routine.
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $lock = new LockHandler('app:download');
        if (!$lock->lock()) {
            $output->writeln('Downloads already in progress');
            return 0;
        }
        $directory = $this->getContainer()->getParameter('download_directory');
        $number = $input->getArgument('number');
        $number = ($number === 'all') ? null : (int)$number;
        $em = $this->getContainer()->get('doctrine.orm.entity_manager');
        $repo = $em->getRepository('AppBundle:Download');
        $downloadIds = $repo
            ->createQueryBuilder('d')
            ->select('d.id')
            ->where('d.downloaded = false')
            ->orderBy('d.created')
            ->setMaxResults($number)
            ->getQuery()
            ->getResult();
        foreach ($downloadIds as $downloadId) {
            $download = $repo->find($downloadId['id']);
            $filePath = $directory . DIRECTORY_SEPARATOR . $download->getFilename();
            $output->write('Downloading ' . $download->getFilename());
            $this->download($download->getUrl(), $filePath, $download->getReferer());
            file_put_contents($filePath . '.txt', $download);
            $output->writeln("   <info>Done</info>");
            $download->setDownloaded(true);
            $em->persist($download);
        }
        $em->flush();
        $lock->release();
    }

    /**
     * Get the container.
     * @return ContainerInterface
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Sets the container.
     * @param ContainerInterface|null $container A ContainerInterface instance or null
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * Download to a file.
     * @param string $url
     * @param string $target
     * @param string|null $referer
     */
    private function download($url, $target, $referer = null)
    {
        static $client = null;
        if (!isset($client)) {
            $client = new Client(['headers' => ['X-Clacks-Overhead: GNU Terry Pratchett']]);
        }
        $options = [];
        if (isset($referer)) {
            $options['headers'] = ['referer' => $referer];
        }
        $client->get($url, $options)
               ->setResponseBody($target)
               ->send();
    }
}
