<?php

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class DeleteCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('app:delete')
            ->setDescription('Delete a download')
            ->addArgument('id', InputOption::VALUE_REQUIRED, 'id of download');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var QuestionHelper $question */
        $question = $this->getHelper('question');
        $id = $input->getArgument('id');
        $em = $this->getContainer()->get('doctrine.orm.entity_manager');
        $download = $em->find('AppBundle:Download', $id);
        $decision = new ConfirmationQuestion(sprintf('Delete %s, found at %s? ', $download->getFilename(), $download->getUrl()), false);
        if ($question->ask($input, $output, $decision)) {
            $em->remove($download);
            $em->flush();
        }
    }
}
