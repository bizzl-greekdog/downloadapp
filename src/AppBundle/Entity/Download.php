<?php

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class Download
 * @package AppBundle\Entity
 * @ORM\Entity
 * @ORM\Table(name="downloads")
 */
class Download
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
     * @ORM\Column(type="string", length=255)
     */
    private $filename = '';

    /**
     * @var string
     * @ORM\Column(type="string", length=255)
     */
    private $referer = '';

    /**
     * @var string
     * @ORM\Column(type="text", length=65535)
     */
    private $comment = '';

    /**
     * @var bool
     * @ORM\Column(type="boolean")
     */
    private $downloaded = false;

    /**
     * @var \DateTime
     * @ORM\Column(type="datetime")
     */
    private $created = null;

    /**
     * @var Metadatum[]
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\Metadatum", mappedBy="download", cascade={"persist", "remove"}, orphanRemoval=true)
     */
    private $metadata;

    /**
     * Download constructor.
     */
    public function __construct()
    {
        $this->metadata = new ArrayCollection();
        $this->created = new \DateTime();
    }

    /**
     * @return \DateTime
     */
    public function getCreated()
    {
        return $this->created;
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
     * @return Download
     */
    public function setUrl($url)
    {
        $this->url = $url;
        return $this;
    }

    /**
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * @param string $filename
     * @return Download
     */
    public function setFilename($filename)
    {
        $this->filename = $filename;
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
     * @return Download
     */
    public function setReferer($referer)
    {
        $this->referer = $referer;
        return $this;
    }

    /**
     * @return string
     */
    public function getComment()
    {
        return $this->comment;
    }

    /**
     * @param string $comment
     * @return Download
     */
    public function setComment($comment)
    {
        $this->comment = $comment;
        return $this;
    }

    /**
     * @return boolean
     */
    public function isDownloaded()
    {
        return $this->downloaded;
    }

    /**
     * @param boolean $downloaded
     * @return Download
     */
    public function setDownloaded($downloaded)
    {
        $this->downloaded = $downloaded;
        return $this;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $title
     * @param string $value
     * @return Metadatum
     */
    public function addMetadatum($title, $value)
    {
        $metadatum = new Metadatum();
        $metadatum->setTitle($title)->setValue($value)->setDownload($this);
        $this->metadata->add($metadatum);
        return $metadatum;
    }

    /**
     * Get metadata
     * @return Collection
     */
    public function getMetadata()
    {
        return $this->metadata;
    }

    public function __toString()
    {
        $result = [];
        foreach ($this->metadata as $metadatum) {
            $result[] = $metadatum->getTitle() . ': ' . $metadatum->getValue();
        }
        $result[] = '======================================';
        $result[] = $this->getComment();
        return implode(PHP_EOL, $result);
    }
}
