<?php

namespace AppBundle\Command;

use Guzzle\Http\Client;
use Guzzle\Plugin\Cookie\CookieJar\FileCookieJar;
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
        $protocol = strtolower(explode(':', $download->getUrl())[0]);
        if (!in_array($protocol, ['http', 'https'])) {
            $this->getContainer()->get('logger')->addError("Invalid protocol \"$protocol\"");
            $download->setFailed(true);
            $em->persist($download);
            return;
        }
        if (!$download->getFilename()) {
            $this->getContainer()->get('logger')->addError('Empty filename');
            $download->setFailed(true);
            $em->persist($download);
            return;
        }
        $saveFilename = $this->makeFilenameSave($download->getFilename());
        $filePath = $directory . DIRECTORY_SEPARATOR . $saveFilename;
        $msg = 'Downloading ' . $download->getFilename();
        if ($saveFilename != $download->getFilename()) {
            $msg .= " as $saveFilename";
        }
        $this->getContainer()->get('logger')->addInfo($msg);
        $output->write($msg);
        try {
            $this->downloadUrl($download->getUrl(), $filePath, $download->getReferer());
            file_put_contents($filePath . '.txt', $download);
            $output->writeln("   <info>Done</info>");
            $download
                ->setDownloaded(true)
                ->setFailed(false);
        } catch (\Exception $e) {
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            $this->getContainer()->get('logger')->addError($e->getMessage());
            $output->writeln("   <error>Failed</error>");
            $download->setFailed(true);
        }
        $download->getChecksum();
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
            ->andWhere('d.failed = false')
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
            $client = new Client(
                [
                    'headers' => [ 'X-Clacks-Overhead: GNU Terry Pratchett'],
                    'cookies' => new FileCookieJar($this->getContainer()->getParameter('cookies'))
                ]
            );
        }
        $options = [];
        try {
            $encoded_url = preg_replace_callback('#://([^/]+)/([^?]+)#', function ($match) {
                return '://' . $match[1] . '/' . join('/', array_map('rawurlencode', explode('/', $match[2])));
            }, $url);
            $response = $client->get($encoded_url, $options)
                            ->setResponseBody($target)
                            ->setHeader('Referer', $referer)
                            ->send();
        } catch (\Exception $e) {
            //die($e->getMessage());
            file_put_contents($target, $url);
        }
    }
}
