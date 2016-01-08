<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Class QueuedUrl
 * @package AppBundle\Entity
 * @ORM\Entity
 * @ORM\Table(name="queued_urls")
 */
class QueuedUrl
{
    /**
     * @var integer
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     * @ORM\Column(type="string", length=1024)
     */
    private $url = '';

    /**
     * @var string
     * @ORM\Column(type="string", length=1024)
     */
    private $referer = '';

    /**
     * @var integer
     * @ORM\Column(type="integer")
     */
    private $priority = 10;

    /**
     * @var \DateTime
     * @ORM\Column(type="datetime")
     */
    private $added = null;

    /**
     * QueuedUrl constructor.
     */
    public function __construct()
    {
        $this->added = new \DateTime();
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @param string $url
     * @return $this
     */
    public function setUrl($url)
    {
        $this->url = $url;
        return $this;
    }

    /**
     * @return string
     */
    public function getReferer()
    {
        return $this->referer;
    }

    /**
     * @param string $referer
     * @return $this
     */
    public function setReferer($referer)
    {
        $this->referer = $referer;
        return $this;
    }

    /**
     * @return int
     */
    public function getPriority()
    {
        return $this->priority;
    }

    /**
     * @param int $priority
     * @return $this
     */
    public function setPriority($priority)
    {
        $this->priority = $priority;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getAdded()
    {
        return $this->added;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }
}
