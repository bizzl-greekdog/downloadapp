<?php
/*
 * Copyright (c) 2016 Benjamin Kleiner
 *
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.  IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */


namespace AppBundle\Services;


use AppBundle\Command\ScanCommand;
use AppBundle\Entity\Download;
use AppBundle\Entity\Notification;
use AppBundle\Entity\QueuedUrl;
use AppBundle\Utilities\ProcessBuffer;
use Doctrine\ORM\EntityManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\ProcessBuilder;

class ScanService
{

    private $pathes = [];
    /** @var EntityManager */
    private $entityManager;
    /** @var LoggerInterface */
    private $logger;
    /** @var string */
    private $scannerEngine;
    /** @var string */
    private $appDir;
    /** @var string */
    private $cookiesFile;

    /**
     * ScanService constructor.
     * @param EntityManager $entityManager
     * @param LoggerInterface $logger
     * @param string $scannerEngine
     * @param string $appDir
     * @param string $cookiesFile
     */
    public function __construct(EntityManager $entityManager, LoggerInterface $logger, $scannerEngine, $appDir, $cookiesFile)
    {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->scannerEngine = $scannerEngine;
        $this->appDir = $appDir;
        $this->cookiesFile = $cookiesFile;
    }

    private function saveScan($scanned, $url, $referer, $autoOpen = false, $allowDupes = false)
    {
        if ($scanned['total']) {
            $results = $this->saveDownloads($scanned['downloads'], $allowDupes);
            $results['queued'] = $this->enqueueUrls($scanned['queued']);
            return $this->successNotification($results, $url, $referer, $autoOpen);
        } else {
            return $this->failureNotification($url, $referer, $autoOpen);
        }
    }

    private function enqueueUrls(array $urls)
    {
        $em = $this->entityManager;
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

    /**
     * @param string $url
     * @param string $referer
     */
    public function scan($url, $referer, $autoOpen = false, $allowDupes = false, $notificationUrl = false, $notificationReferer = false)
    {
        $logger = $this->logger;
        $engine = $this->scannerEngine;
        $appRoot = $this->appDir;

        $notifications = [];

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
        if (file_exists($this->cookiesFile)) {
            $processBuilder->add("--cookies-file={$this->cookiesFile}");
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
                    $this->entityManager->persist($n);
                    $notifications[] = $n;
                } elseif (substr($line, 0, 5) == 'ALERT') {
                    $n = new Notification(true);
                    $n
                        ->setType('info')
                        ->setTitle('Information')
                        ->setText(substr($line, 6))
                        ->setUrl($url)
                        ->setReferer($referer);
                    $this->entityManager->persist($n);
                    $notifications[] = $n;
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
            $logger->error('Scanner failed hard');
        }

        $stderr = $process->getErrorOutput();
        if ($stderr) {
            foreach (explode("\n", $stderr) as $line) {
                $logger->error($line);
            }
        }

        $result = ['downloads' => $downloads, 'queued' => $queued, 'total' => count($queued) + count($downloads)];
        $notifications[] = $this->saveScan($result, $notificationUrl ?: $url, $notificationReferer ?: $referer, $autoOpen, $allowDupes);
        return $notifications;
    }

    private function getPath($varname, $default)
    {
        return $default;
        /*if (!isset($this->pathes[$varname])) {
            try {
                $this->pathes[$varname] = $this->getContainer()->getParameter($varname);
            } catch (\Exception $e) {
                $this->pathes[$varname] = $default;
            }
        }
        return $this->pathes[$varname];*/
    }

    private function saveDownloads(array $downloads, $allowDupes = false)
    {
        $logger = $this->logger;
        $em = $this->entityManager;
        $result = ['saved' => 0, 'skipped' => 0];
        foreach ($downloads as $download) {
            $downloadEntity = new Download();
            $downloadEntity
                ->setUrl($download['url'])
                ->setFilename($download['filename'])
                ->setReferer($download['referer'])
                ->setComment($download['comment'])
                ->setDownloaded(false);
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
        $this->entityManager->persist($notification);
        $this->logger->info($notification->getText());
        return $notification;
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
        $this->entityManager->persist($notification);
        $this->logger->error($notification->getText() . " (Referer: {$referer})");
        return $notification;
    }
}
