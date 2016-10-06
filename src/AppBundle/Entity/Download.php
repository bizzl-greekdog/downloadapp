<?php

namespace AppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Karwana\Mime\Mime;

/**
 * Class Download
 * @package AppBundle\Entity
 * @ORM\Entity
 * @ORM\Table(name="downloads")
 * @ORM\HasLifecycleCallbacks
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
     * @ORM\Column(type="string", length=1024)
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
     * @var string
     * @ORM\Column(type="string", length=32, nullable=true)
     */
    private $checksum = null;

    /**
     * @var Metadatum[]
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\Metadatum", mappedBy="download", cascade={"persist", "remove"}, orphanRemoval=true)
     */
    private $metadata;

    /**
     * @var bool
     * @ORM\Column(type="boolean", options={"default" = 0})
     */
    private $failed = false;

    /**
     * Download constructor.
     */
    public function __construct()
    {
        $this->metadata = new ArrayCollection();
        $this->created = new \DateTime();
    }

    /**
     * @return string
     */
    public function getChecksum()
    {
        if (!isset($this->checksum)) {
            $this->calculateChecksum();
        }
        return $this->checksum;
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
        $filename = str_replace(
            ['<', '>', ':',   '"', '/',   '\\',  '|',   '?', '*'],
            ['(', ')', ' - ', "'", ' - ', ' - ', ' - ', '',  '+'],
            $filename
        );
        $this->filename = $filename;
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
        $this->checksum = null;
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

    /**
     * @ORM\PrePersist
     */
    public function calculateChecksum()
    {
        $this->checksum = md5($this->getUrl() . $this->getReferer());
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
        $this->checksum = null;
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
     * @return int|string
     */
    public function getMimetype()
    {
        return Mime::getTypeForExtension($this->getExtension());
    }

    /**
     * @return string
     */
    public function getExtension()
    {
        return pathinfo($this->getFilename(), PATHINFO_EXTENSION);
    }

    /**
     * @return boolean
     */
    public function isFailed()
    {
        return $this->failed;
    }

    /**
     * @param boolean $failed
     * @return Download
     */
    public function setFailed($failed)
    {
        $this->failed = $failed;
        return $this;
    }
}
