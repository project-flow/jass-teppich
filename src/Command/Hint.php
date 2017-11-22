<?php

namespace App\Command;


use App\Repository;
use function Jass\Hand\ordered;
use function Jass\Strategy\card;
use function Jass\Strategy\cardStrategy;
use function Jass\Strategy\firstCardOfTrick;
use function Jass\Strategy\firstCardOfTrickStrategy;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class Hint extends Command
{
    /**
     * @var Repository
     */
    private $repository;

    /**
     * @param Repository $repository
     */
    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('jass:hint')->setDescription('Gives a hint for the ');
        $this->addArgument('game', InputArgument::REQUIRED, 'Name of the game');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $gameName = $input->getArgument('game');
        $game = $this->repository->loadGame($gameName);

        $io = new SymfonyStyle($input, $output);
        $io->writeln('Hand:');
        $io->listing(ordered($game->currentPlayer->hand, $game->style->orderFunction()));

        if ($game->currentTrick) {
            $card = card($game->currentPlayer, $game->currentTrick, $game->style);
            $strategy = cardStrategy($game->currentPlayer, $game->currentTrick, $game->style);
        } else {
            $card = firstCardOfTrick($game->currentPlayer, $game->style);
            $strategy = firstCardOfTrickStrategy($game->currentPlayer, $game->style);
        }

        $strategyName = substr(get_class($strategy), strrpos(get_class($strategy), '\\') + 1);
        $io->writeln('Strategy: ' . $strategyName);
        $io->writeln('Card: ' . $card);

    }

}