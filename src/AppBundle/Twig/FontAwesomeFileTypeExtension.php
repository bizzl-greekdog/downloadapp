<?php
/**
 * Created by PhpStorm.
 * User: benjamin
 * Date: 1/22/16
 * Time: 10:59 PM
 */

namespace AppBundle\Twig;


class FontAwesomeFileTypeExtension extends \Twig_Extension
{

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'font_awesome_file_type_extension';
    }

    public function getFilters()
    {
        return [
            new \Twig_SimpleFilter('faFileIcon', [$this, 'getFileTypeIcon']),
        ];
    }

    public function getFileTypeIcon($mime)
    {
        if (trim($mime)) {
            list($group, $type) = explode('/', $mime);
            if ($group === 'image') {
                return 'fa-file-image-o';
            }
        }
        return 'fa-file-o';
    }
}
