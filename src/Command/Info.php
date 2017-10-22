<?php

namespace App\Command;

use App\Repository;
use Jass\Entity\Turn;
use function Jass\Game\hasStarted;
use function Jass\Game\isReady;
use function Jass\Game\teamPoints;
use function Jass\Game\teams;
use function Jass\Trick\isFinished;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Jass\Entity\Player as PlayerEntity;

class Info extends Command
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
        $this->setName('jass:info')->setDescription('Information about the game');
        $this->addArgument('game', InputArgument::REQUIRED, 'Name of the game');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $gameName = $input->getArgument('game');
        $game = $this->repository->loadGame($gameName);

        $io = new SymfonyStyle($input, $output);

        if (!$game->players) {
            $io->warning('Players not set. Use jass:player');
            return;
        }
        $names = array_map(function(PlayerEntity $player){
            return $player->name;
        }, $game->players);
        $io->writeln("Players are: " . implode(', ', $names));

        if (!$game->style) {
            $io->warning('Game style not set. Use jass:style');
            return;
        }
        $io->writeln('Game style: ' . $game->style->name);

        if (!$game->players[0]->hand && !$game->playedTricks) {
            $io->warning('Cards are not dealt. Use jass:deal');
            return;
        }

        if (isReady($game) && !hasStarted($game)) {
            $io->success('Game is ready to start. Use jass:play:*');
            return;
        }

        $io->writeln('Played tricks: ' . count($game->playedTricks));

        $teams = teams($game);
        foreach ($teams as $team) {
            $io->writeln('Team ' . $team . ' has ' . teamPoints($team, $game) . ' points.');
        }

        $io->writeln('');
        if (\Jass\Game\isFinished($game)) {
            $io->writeln('Game is finished.');
        } else {
            $io->writeln('Next card should be from ' . $game->currentPlayer->name);

            if ($game->currentTrick && !isFinished($game->currentTrick)) {
                if ($game->currentTrick->leadingSuit) {
                    $io->writeln('Leading suit is ' . $game->currentTrick->leadingSuit);
                }
                if ($game->currentTrick->turns) {
                    $io->writeln('Played cards:');
                    $io->listing(array_map(function(Turn $turn) {
                        return $turn->card . ' played by ' . $turn->player;
                    }, $game->currentTrick->turns));
                }
            }
        }

    }

}