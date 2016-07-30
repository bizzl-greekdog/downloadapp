<?php

namespace AppBundle\Controller;

use AppBundle\Command\DownloadCommand;
use AppBundle\Entity\Download;
use AppBundle\Entity\Metadatum;
use AppBundle\Entity\Notification;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class DefaultController extends Controller
{
    const PAGE_SIZE = 15;

    public function allAction($page)
    {
        return $this->indexAction($page, true);
    }

    public function indexAction($page, $showDownloaded = false)
    {
        $em = $this->container->get('doctrine.orm.entity_manager');
        $repo = $em->getRepository('AppBundle:Download');
        $total = $repo
            ->createQueryBuilder('d')
            ->select('count(d.id)');
        if (!$showDownloaded) {
            $total->andWhere('d.downloaded = false');
        }
        $total = $total
            ->getQuery()
            ->getSingleScalarResult();
        $pageCount = ceil(abs($total - 1) / self::PAGE_SIZE);

        if ($page > $pageCount) {
            return $this->redirectToRoute($showDownloaded ? 'app_list_all_downloads' : 'app_list_downloads', ['page' => $pageCount]);
        } elseif ($page < 1) {
            return $this->redirectToRoute($showDownloaded ? 'app_list_all_downloads' : 'app_list_downloads', ['page' => 1]);
        }

        $offset = ($page - 1) * self::PAGE_SIZE;
        $queryBuilder = $repo->createQueryBuilder('d')
                             ->select('d.id')
                             ->orderBy('d.created')
                             ->setMaxResults(self::PAGE_SIZE)
                             ->setFirstResult($offset);
        if (!$showDownloaded) {
            $queryBuilder->andWhere('d.downloaded = false');
        }
        $downloadIds = $queryBuilder
            ->getQuery()
            ->getResult();
        $downloads = [];
        foreach ($downloadIds as $downloadId) {
            $downloads[] = $repo->find($downloadId['id']);
        }
        return $this->render(
            'AppBundle:Default:index.html.twig',
            [
                'page'           => $page,
                'firstPage'      => $page == 1,
                'lastPage'       => $page == $pageCount,
                'downloads'      => $downloads,
                'total'          => $total,
                'pageCount'      => $pageCount,
                'showDownloaded' => $showDownloaded,
                'stats'          => $this->container->get('app.stats')->getStats(),
            ]
        );
    }

    public function downloadAction(Request $request, $id)
    {
        $downloadCmd = new DownloadCommand();
        $downloadCmd->setContainer($this->container);
        $downloadCmd->download(new NullOutput(), $id);
        $this->get('doctrine.orm.entity_manager')->flush();
        return $this->redirect($request->headers->get('referer'));
    }

    public function deleteAction(Request $request, $id)
    {
        $em = $this->get('doctrine.orm.entity_manager');
        $repo = $em->getRepository('AppBundle:Download');
        $download = $repo->find($id);
        $em->remove($download);
        $em->flush();
        return $this->redirect($request->headers->get('referer'));
    }

    public function notificationAction($id)
    {
        $notification = $this
            ->container
            ->get('doctrine.orm.entity_manager')
            ->getRepository('AppBundle:Notification')
            ->find($id);
        return $this->render(
            'AppBundle:Default:notification.html.twig',
            [
                'notification' => $notification,
                'stats'        => $this->container->get('app.stats')->getStats(),
            ]
        );
    }

    public function putDownloadAction(Request $request)
    {
        $items = json_decode($request->getContent(), JSON_OBJECT_AS_ARRAY);
        if (isset($items['url'])) {
            $items = [$items];
        }

        $em = $this->container->get('doctrine.orm.default_entity_manager');
        $skipped = 0;
        $saved = 0;
        foreach ($items as $item) {
            $downloadEntity = new Download();
            $downloadEntity
                ->setUrl($item['url'])
                ->setComment($item['comment'])
                ->setFilename($item['filename'])
                ->setReferer($item['referer']);
            foreach ($item['metadata'] as $key => $value) {
                $downloadEntity->addMetadatum($key, $value);
            }
            $existQuery = $em->getRepository('AppBundle:Download')
                             ->createQueryBuilder('d')
                             ->select('d.id')
                             ->where('d.checksum = :checksum')
                             ->getQuery();
            $existQuery->execute(['checksum' => $downloadEntity->getChecksum()]);
            if (count($existQuery->getArrayResult()) === 0) {
                $em->persist($downloadEntity);
                $saved++;
            } else {
                $skipped++;
            }
        }
        $notification = new Notification($saved > 100);
        $notification
            ->setType('info')
            ->setTitle('Scan successful')
            ->setText("{$saved} saved, {$skipped} skipped, 0 enqueued");
        $em->persist($notification);
        $em->flush();
        return new JsonResponse($notification);
    }
}
