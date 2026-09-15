<?php

namespace App\Command;

use App\Repository\TemporaryQueryResultRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:query-results:purge', description: 'Delete expired private custom SELECT results')]
final class PurgeQueryResultsCommand extends Command
{
    public function __construct(private readonly TemporaryQueryResultRepository $results)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(sprintf('Deleted %d expired query result(s).', $this->results->purgeExpired()));

        return Command::SUCCESS;
    }
}
