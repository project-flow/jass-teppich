<?php

namespace App\Command;

use App\Repository;
use Jass\Entity\Card;
use Jass\Entity\Trick;
use function Jass\Game\isFinished;
use function Jass\Game\isReady;
use function Jass\Game\teamPoints;
use function Jass\Game\teams;
use function Jass\Hand\last;
use Jass\Message\Turn;
use Jass\MessageHandler;
use function Jass\Strategy\card;
use function Jass\Trick\winningTurn;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class Play extends Command
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
        $this->setName('jass:play')->setDescription('Play a card or until a certain event');
        $this->addArgument('game', InputArgument::REQUIRED, 'Name of the game');
        $this->addArgument('until', InputArgument::REQUIRED, 'Until when you want to play. Possibilities: turn, trick, player, game');
        $this->addArgument('card', InputArgument::OPTIONAL, 'Card. Use Shortcuts or auto', 'auto');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $gameName = $input->getArgument('game');
        $game = $this->repository->loadGame($gameName);

        if (!isReady($game)) {
            throw new \InvalidArgumentException('Game ' . $gameName . ' is not ready.');
        }

        $until = strtolower($input->getArgument('until'));
        $validUntils = ['turn', 'trick', 'player', 'game'];
        if (!in_array($until, $validUntils)) {
            throw new \InvalidArgumentException('Until has to be one of ' . implode(', ', $validUntils));
        }
        $manualPlayer = $game->players[0];

        $cardName = strtolower($input->getArgument('card'));
        $card = null;
        if ($cardName != 'auto') {
            $card = Card::shortcut($cardName);
        }

        $messageHandler = new MessageHandler();
        $io = new SymfonyStyle($input, $output);
        do {
            if (is_null($card)) {
                $card = card($game);
            }
            $io->writeln($game->currentPlayer . ' plays ' . $card);
            $message = new Turn();
            $message->card = $card;

            $game = $messageHandler->handle($game, $message);

            $this->repository->recordMessage($game->name, $message);

            if (!$game->currentTrick) {
                /** @var Trick $finishedTrick */
                $finishedTrick = last($game->playedTricks);
                $winningTurn = winningTurn($finishedTrick, $game->style->orderFunction());
                $io->writeln($winningTurn->player . ' won the trick with card ' . $winningTurn->card);
            }

            if (isFinished($game)) {
                $io->writeln('Game is finished!');
                $teams = teams($game);
                foreach ($teams as $team) {
                    $io->writeln('Team ' . $team . ' has ' . teamPoints($team, $game) . ' points.');
                }
            }

            $card = null;

            $timeToSayGoodbye = false;
            switch ($until) {
                case "turn":
                    $timeToSayGoodbye = true;
                    break;
                case "trick":
                    $timeToSayGoodbye = !$game->currentTrick;
                    break;
                case "player":
                    $timeToSayGoodbye = $game->currentPlayer === $manualPlayer;
                    break;
                case "game":
                    $timeToSayGoodbye = isFinished($game);
                    break;
            }

        } while (!$timeToSayGoodbye);
    }


}