<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Class Notification
 * @package AppBundle\Entity
 * @ORM\Entity()
 * @ORM\Table(name="notifications")
 */
class Notification implements \JsonSerializable
{
    /**
     * @var integer
     * @ORM\Id()
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     * @ORM\Column(type="text")
     */
    private $text = '';

    /**
     * @var string
     * @ORM\Column(type="string", length=255)
     */
    private $title = '';

    /**
     * @var string
     * @ORM\Column(type="string", length=10)
     */
    private $type = 'info';

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
     * @var \DateTime
     * @ORM\Column(type="datetime")
     */
    private $created = null;

    /**
     * Notification constructor.
     */
    public function __construct()
    {
        $this->created = new \DateTime();
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return \DateTime
     */
    public function getCreated()
    {
        return $this->created;
    }

    function __toString()
    {
        return json_encode($this);
    }

    /**
     * Specify data which should be serialized to JSON
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    function jsonSerialize()
    {
        return [
            'type'    => $this->type,
            'title'   => $this->title,
            'text'    => $this->text,
            'url'     => $this->url,
            'referer' => $this->referer,
            'id'      => $this->id,
        ];
    }

    /**
     * @return string
     */
    public function getText()
    {
        return $this->text;
    }

    /**
     * @param string $text
     * @return $this
     */
    public function setText($text)
    {
        $this->text = $text;
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
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $type
     * @return $this
     */
    public function setType($type)
    {
        $this->type = $type;
        return $this;
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

}
