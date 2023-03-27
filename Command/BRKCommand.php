<?php

namespace CommonGateway\BRKBundle\Command;

use CommonGateway\BRKBundle\Service\BRKService;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @Author Wilco Louwerse <wilco@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Command
 */
class BRKCommand extends Command
{
    // the name of the command (the part after "bin/console")
    /**
     * @var string
     */
    protected static $defaultName = 'brk:fileSystem:read'; // Todo: Better command name

    /**
     * @var BRKService
     */
    private BRKService  $brkService;

    /**
     * @param BRKService $brkService The rating service
     */
    public function __construct(BRKService $brkService)
    {
        $this->brkService = $brkService;
        parent::__construct();
    }//end __construct()

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setDescription('This command triggers BRKService, converting xml file data to ObjectEntities.')
            ->setHelp('This command allows you to convert xml files from the BRK fileSystem to ObjectEntities (or update existing ObjectEntities). This command requires a filename.')
            ->addOption('filename', 'f', InputOption::VALUE_REQUIRED, 'Rate a single component by id');
    }//end configure()

    /**
     * @param InputInterface  $input  The input
     * @param OutputInterface $output The output
     *
     * @throws Exception
     *
     * @return int 1 for SUCCESS & 0 for FAILURE
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);

        // Handle the command options
        $filename = $input->getOption('filename', false);

        $style->info('Execute BRK fileSystem read Command');

        if (empty($filename)) {
            $style->error('Please use the option --filename or -f to specify a filename.');
            return Command::FAILURE;
        }
    
        try {
            // Todo: do we want to use query for this?
            $result = $this->brkService->BRKHandler(['query' => ['filename' => $filename]], []);
            
            $style->block("Created (or updated) ObjectEntities for ".count($result)." references.", array_keys($result));
        } catch (Exception $exception) {
            $style->error('Command failed: '.$exception->getMessage());
            
            return Command::FAILURE;
        }
    
        $style->success('Command was successful');
        return Command::SUCCESS;
    }//end execute()
}//end class
