<?php

namespace App\Command;


use App\Repository;
use Jass\Entity\Game;
use function Jass\Game\isReady;
use function Jass\Hand\ordered;
use Jass\Knowledge\BockKnowledge;
use Jass\Style\TopDown;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class Knowledge extends Command
{
    private $repository;

    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('jass:knowledge');
        $this->setDescription('Shows knowledge of a certain situation');
        $this->addArgument('game', InputArgument::REQUIRED, 'The name of the game');
        $this->addArgument('trick', InputArgument::REQUIRED, 'Which trick 1-9');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $game = $input->getArgument('game');
        $trick = $input->getArgument('trick');
        $game = $this->repository->loadGame($game, function(Game $game, $message, $step) use ($trick) {
            if (!isReady($game)) {
                return true;
            }
            if (count($game->playedTricks) < $trick - 1) {
                return true;
            }
            return  $game->currentPlayer !== $game->players[0];
        });

        $io = new SymfonyStyle($input, $output);

        $io->title('Style: ' . $game->style->name);
        $io->title('Hand');
        $style = $game->style ?? new TopDown();
        $io->listing(ordered($game->currentPlayer->hand, $style->orderFunction()));

        $bockKnowledge = BockKnowledge::analyze($game);
        $bockCards = [];
        foreach ($bockKnowledge->bockCards as $suit => $card) {
            $bockCards[] = $suit . ": " . $card;
        }
        $potential = [];
        foreach ($bockKnowledge->suitPotential as $suit => $neededCards) {
            $potential[] = $suit . ": " . $neededCards;
        }

        $io->title('BockKnowledge');
        $io->table(['what', 'description', 'value'], [
            ['bockCards', 'Highest card of every suit', implode(", ", $bockCards)],
            ['potential', 'How many cards need to be played until there is a bock card ', implode(", ", $potential)]
        ]);

    }

}