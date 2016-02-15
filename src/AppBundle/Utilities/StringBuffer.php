<?php
/**
 * Created by PhpStorm.
 * User: benjamin
 * Date: 2/15/16
 * Time: 2:05 PM
 */

namespace AppBundle\Utilities;


class StringBuffer
{
    /** @var string  */
    private $buffer = '';

    /** @var  callable */
    private $callback;

    /**
     * StringBuffer constructor.
     * @param callable $callback
     */
    public function __construct(callable $callback = null)
    {
        $this->callback = $callback;
    }

    public function add($string)
    {
        $this->buffer .= $string;
        $this->splitLines();
    }

    private function splitLines()
    {
        while (($i = strpos($this->buffer, PHP_EOL)) !== false) {
            $line = substr($this->buffer, 0, $i);
            $this->buffer = substr($this->buffer, $i+strlen(PHP_EOL));
            $this->callCallback($line);
        }
    }

    public function close()
    {
        $this->splitLines();
        if (strlen($this->buffer)) {
            $this->callCallback($this->buffer);
        }
    }

    private function callCallback($string)
    {
        if (is_callable($this->callback)) {
            call_user_func($this->callback, $string);
        }
    }


}
