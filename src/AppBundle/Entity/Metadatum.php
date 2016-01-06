<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Class Metadatum
 * @package AppBundle\Entity
 * @ORM\Entity
 * @ORM\Table(name="metadata")
 */
class Metadatum
{
    /**
     * @var integer
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var Download
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Download", inversedBy="metadata")
     */
    private $download;

    /**
     * @var string
     * @ORM\Column(type="string", length=255)
     */
    private $title = '';

    /**
     * @var string
     * @ORM\Column(type="string", length=255)
     */
    private $value = '';

    /**
     * @return Download
     */
    public function getDownload()
    {
        return $this->download;
    }

    /**
     * @param Download $download
     * @return $this
     */
    public function setDownload(Download $download)
    {
        $this->download = $download;
        return $this;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param string $title
     * @return $this
     */
    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param string $value
     * @return $this
     */
    public function setValue($value)
    {
        $this->value = $value;
        return $this;
    }

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }
}
