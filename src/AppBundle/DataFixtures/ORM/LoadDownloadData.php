<?php
/**
 * Created by PhpStorm.
 * User: benjamin
 * Date: 1/4/16
 * Time: 1:35 PM
 */

namespace AppBundle\DataFixtures\ORM;


use AppBundle\Entity\Download;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

class LoadDownloadData implements FixtureInterface
{

    /**
     * Load data fixtures with the passed EntityManager
     *
     * @param ObjectManager $manager
     */
    public function load(ObjectManager $manager)
    {
        $download = new Download();
        $download
            ->setReferer('http://apod.nasa.gov/apod/ap151207.html')
            ->setFilename('apod_CometCatalina_Hemmerich_1821.jpg')
            ->setComment("Explanation: Comet Catalina is ready for its close-up.
The giant snowball from the outer Solar System, known formally as C/2013 US10 (Catalina), rounded the Sun last month and is now headed for its closest approach to Earth in January.
With the glow of the Moon now also out of the way, morning observers in Earth's northern hemisphere are getting their best ever view of the new comet.
And Comet Catalina is not disappointing.
Although not as bright as early predictions, the comet is sporting both dust (lower left) and ion (upper right) tails, making it an impressive object for binoculars and long-exposure cameras.
The featured image was taken last week from the Canary Islands, off the northwest coast of Africa.
Sky enthusiasts around the world will surely be tracking the comet over the next few months to see how it evolves.")
            ->setUrl('http://apod.nasa.gov/apod/image/1512/CometCatalina_Hemmerich_960.jpg');

        $manager->persist($download);
        $manager->persist($download->addMetadatum('Title', 'Comet Catalina Emerges'));
        $manager->persist($download->addMetadatum('Artist', 'Fritz Helmut Hemmerich'));
        $manager->persist($download->addMetadatum('Original Filename', 'CometCatalina_Hemmerich_1821.jpg'));
        $manager->persist($download->addMetadatum('Source', 'http://apod.nasa.gov/apod/ap151207.html'));
        $manager->flush();
    }
}
