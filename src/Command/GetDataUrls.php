<?php

namespace CommonGateway\BRKBundle\Command;

use CommonGateway\BRKBundle\Service\GdsService;
use CommonGateway\CoreBundle\Service\InstallationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @Author Ruben van der Linde <ruben@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Command
 */
class GetDataUrls extends Command
{

    protected static $defaultName = 'brk:get:urls';

    private $gdsService;

    private EntityManagerInterface $entityManager;


    public function __construct(GdsService $gdsService, EntityManagerInterface $entityManager)
    {
        $this->gdsService    = $gdsService;
        $this->entityManager = $entityManager;
        parent::__construct();

    }//end __construct()


    protected function configure(): void
    {
        $this
            ->setDescription('This command retrieves data urls from a GDS source')
            ->addArgument('source', InputArgument::REQUIRED, 'The source to request')
            ->addArgument('location', InputArgument::REQUIRED, 'The location on the source to request')
            ->setHelp('This command allows you to run further installation an configuration actions afther installing a plugin');

    }//end configure()


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $source = $this->entityManager->getRepository('App:Gateway')->find($input->getArgument('source'));

        $date = new DateTime('21-02-2023 00:00:00');
        $this->gdsService->getData($source, $input->getArgument('location'), $date, true);

    }//end execute()


}//end class
