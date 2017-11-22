<?php

namespace App\Command;


use App\Repository;
use Jass\Entity\Game;
use Jass\Entity\Trick;
use Jass\Entity\Turn;
use Jass\Entity\Player;
use function Jass\Game\teamPoints;
use function Jass\Game\teams;
use function Jass\Hand\last;
use function Jass\Hand\ordered;
use Jass\Message\Message;
use function Jass\Strategy\card;
use function Jass\Strategy\cardStrategy;
use function Jass\Trick\points;
use function Jass\Trick\winningTurn;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class Replay extends Command
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
        $this->setName('jass:replay')->setDescription('Replays a match from a view of a player');
        $this->addArgument('game', InputArgument::REQUIRED, 'Name of the game');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $gameName = $input->getArgument('game');

        $game = $this->repository->loadGame($gameName);

        if (!$game->playedTricks || count($game->playedTricks) < 1) {
            $io->warning('Replay works only if at least one trick is already played. Use jass:play');
            return;
        }


        $io->writeln('Players:');
        $io->listing(array_map(function (Player $player) {
            $strategies = array_map([$this, 'getClassBaseName'], $player->strategies);
            return $player->name . '; Strategies: ' . implode(', ', $strategies);
        }, $game->players));

        $player = 0;
        $io->writeln('You are ' . $game->players[$player]);

        $this->repository->loadGame($gameName, function(Game $game, Message $message) use ($io, $player) {
            if ($message instanceof \Jass\Message\Turn && $game->currentPlayer === $game->players[$player]) {
                $io->writeln('Hand of ' . $game->players[$player]);
                $io->listing(ordered($game->players[$player]->hand, $game->style->orderFunction()));

                $card = card($game);
                $strategy = cardStrategy($game);
                $io->writeln('Card choosen by ' . $this->getClassBaseName($strategy) . ': ' . $card);
            }
        }, function(Game $game, Message $message) use ($io) {
            if ($message instanceof \Jass\Message\Turn) {
                /** @var Trick $trick */
                $trick = $game->currentTrick ?? last($game->playedTricks);
                /** @var Turn $turn */
                $turn = last($trick->turns);
                $io->writeln(' => ' . $turn->player . ' plays ' . $turn->card);
                if (!$game->currentTrick) {
                    $winnerTurn = winningTurn($trick, $game->style->orderFunction());
                    $io->writeln(points($trick, $game->style->pointFunction()) . ' points won by ' . $winnerTurn->player . ' with ' . $winnerTurn->card . '.');
                }
            }
        });

        $io->writeln('');
        $teams = teams($game);

        foreach ($teams as $team) {
            $io->writeln($team . ' has ' . teamPoints($team, $game) . ' points');
        }
    }

    private function getClassBaseName($class)
    {
        $className = (is_string($class) ? $class : get_class($class));
        return substr($className, strrpos($className, '\\') + 1);
    }


}