<?php

namespace App\Command;

use App\Repository;
use function Jass\Hand\first;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Jass\Entity\Player as PlayerEntity;

class Brain extends Command
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
        $this->setName('jass:brain')->setDescription('What a player knows');
        $this->addArgument('game', InputArgument::REQUIRED, 'Name of the game');
        $this->addArgument('player', InputArgument::REQUIRED, 'Name of the player');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $gameName = $input->getArgument('game');
        $game = $this->repository->loadGame($gameName);

        $playerName = $input->getArgument('player');

        /** @var PlayerEntity $player */
        $player = first(array_filter($game->players, function (PlayerEntity $player) use ($playerName) {
            return strtolower(trim($player->name)) == strtolower($playerName);
        }));

        if (!$player) {
            throw new \InvalidArgumentException('Unknown player with name ' . $playerName);
        }

        $io = new SymfonyStyle($input, $output);
        if (!$player->brain) {
            $io->writeln('Player ' . $player . ' knows nothing.');
        } else {
            $io->writeln('This is what ' . $player . ' knows:');
            $values = $player->brain;
            $io->listing(array_reduce(array_keys($values), function ($entries, $key) use ($values) {
                $entries[] = $key . ': ' . ((is_array($values[$key])) ? implode(', ', $values[$key]) : $values[$key]);
                return $entries;
            }, []));
        }
    }

}