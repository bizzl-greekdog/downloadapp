<?php

namespace AppBundle\Command;

use Guzzle\Http\Client;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\LockHandler;

/**
 * Console command to download a number of files listed in the downloads table.
 * @package AppBundle\Command
 */
class DownloadCommand extends ContainerAwareCommand
{
    /**
     * @param OutputInterface $output
     * @param $repo
     * @param $downloadId
     * @param $directory
     * @param $em
     */
    public function download(OutputInterface $output, $downloadId)
    {
        $directory = $this->getContainer()->getParameter('download_directory');
        $em = $this->getContainer()->get('doctrine.orm.entity_manager');
        $repo = $em->getRepository('AppBundle:Download');
        $download = $repo->find($downloadId);
        $saveFilename = $this->makeFilenameSave($download->getFilename());
        $filePath = $directory . DIRECTORY_SEPARATOR . $saveFilename;
        $output->write('Downloading ' . $download->getFilename());
        if ($saveFilename != $download->getFilename()) {
            $output->write(" as $saveFilename");
        }
        $this->downloadUrl($download->getUrl(), $filePath, $download->getReferer());
        file_put_contents($filePath . '.txt', $download);
        $output->writeln("   <info>Done</info>");
        $download->setDownloaded(true);
        $em->persist($download);
    }

    /**
     * Setup routine.
     */
    protected function configure()
    {
        $this
            ->setName('app:download')
            ->setDescription('Download a number of files')
            ->addOption('count', 'c', InputOption::VALUE_OPTIONAL, 'Number of files to download', 5);
    }

    /**
     * Main routine.
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $lock = new LockHandler($this->getName());
        if (!$lock->lock()) {
            $output->writeln($this->getName() . ' already in progress');
            return -1;
        }

        $directory = $this->getContainer()->getParameter('download_directory');
        $number = $input->getOption('count');
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
            $this->download($output, $downloadId['id']);
        }
        $em->flush();

        $lock->release();
        return 0;
    }

    private function makeFilenameSave($inFilename)
    {
        $directory = $this->getContainer()->getParameter('download_directory');
        if (!file_exists("$directory/$inFilename")) {
            return $inFilename;
        }
        $i = 1;
        $extension = pathinfo($inFilename, PATHINFO_EXTENSION);
        $basename = pathinfo($inFilename, PATHINFO_FILENAME);
        while (file_exists("$directory/$basename.$i.$extension")) {
            $i++;
        }
        return "$basename.$i.$extension";
    }

    /**
     * Download to a file.
     * @param string $url
     * @param string $target
     * @param string|null $referer
     */
    private function downloadUrl($url, $target, $referer = null)
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
