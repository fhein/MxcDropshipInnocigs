<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace MxcDropshipInnocigs\Commands;

use Mxc\Shopware\Plugin\Service\ServicesTrait;
use MxcDropshipInnocigs\Import\ImportClient;
use Shopware\Commands\ShopwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class ImportCommand extends ShopwareCommand
{
    protected $log;

    use ServicesTrait;
    /*
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('mxcdsi:import')
            ->setDescription('Import products from Innocigs')
            /*  ->addArgument(
                  'filepath',
                  InputArgument::REQUIRED,
                  'Path to file to read data from.'
              )*/
            ->setHelp(<<<EOF
The <info>%command.name%</info> imports products from Innocigs.
EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<info>Importing products from InnoCigs ...</info>');
        $this->getServices();

        try {
            $this->services->get(ImportClient::class)->import();
        } catch (Throwable $e) {
            $output->writeln('<merror>' . $e->getMessage() . '</merror>');
        }
        $output->writeln('<info>Done</info>');
    }
}