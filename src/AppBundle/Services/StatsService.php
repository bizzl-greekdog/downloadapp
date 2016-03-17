<?php

namespace AppBundle\Services;


use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

class StatsService implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function getStats()
    {
        $em = $this->container->get('doctrine.orm.entity_manager');
        $downloadRepository = $em->getRepository('AppBundle:Download');

        $total = $downloadRepository
            ->createQueryBuilder('d')
            ->select('count(d.id)')
            ->getQuery()
            ->getSingleScalarResult();
        $notDownloaded = $downloadRepository
            ->createQueryBuilder('d')
            ->select('count(d.id)')
            ->where('d.downloaded = false')
            ->getQuery()
            ->getSingleScalarResult();;
        $downloaded = $total - $notDownloaded;
        $enqueued = $em
            ->getRepository('AppBundle:QueuedUrl')
            ->createQueryBuilder('q')
            ->select('count(q.id)')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'Total'          => $total,
            'Downloaded'     => $downloaded,
            'Not Downloaded' => $notDownloaded,
            'Enqueued'       => $enqueued,
        ];
    }
}
