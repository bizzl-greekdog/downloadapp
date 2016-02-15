<?php
/**
 * Created by PhpStorm.
 * User: benjamin
 * Date: 2/15/16
 * Time: 2:42 PM
 */

namespace AppBundle\Utilities;


use Symfony\Component\Process\Process;

class ProcessBuffer
{
    /** @var  StringBuffer */
    private $stdout;

    /** @var  StringBuffer */
    private $stderr;

    /**
     * ProcessBuffer constructor.
     */
    public function __construct(callable $stdout, callable $stderr = null)
    {
        $this->stdout = new StringBuffer($stdout);
        $this->stderr = new StringBuffer($stderr);
    }

    public function getProcessCallback()
    {
        return [$this, 'onOutput'];
    }

    public function onOutput($type, $output)
    {
        if ($type == Process::OUT) {
            $this->stdout->add($output);
        } else {
            $this->stderr->add($output);
        }
    }

    public function close()
    {
        $this->stderr->close();
        $this->stdout->close();
    }

}
