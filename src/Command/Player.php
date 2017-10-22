<?php

namespace App\Command;

use App\Repository;
use Jass\Message\PlayerSetup;
use Jass\MessageHandler;
use function Jass\Player\byNames;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Jass\Entity\Player as PlayerEntity;

class Player extends Command
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
        $this->setName('jass:player')->setDescription('Sets up default players');
        $this->addArgument('game', InputArgument::REQUIRED, 'Name of the game');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $gameName = $input->getArgument('game');
        $game = $this->repository->loadGame($gameName);

        $message = new PlayerSetup();
        $message->players = byNames('Ueli, Fritz, Hans, Sepp');
        $message->starter = $message->players[0];

        $this->repository->recordMessage($gameName, $message);

        $messageHandler = new MessageHandler();
        $game = $messageHandler->handle($game, $message);

        $io = new SymfonyStyle($input, $output);

        $io->text('Players are set up for game ' . $game->name);
        $io->listing(array_map(function (PlayerEntity $player) {
            return $player->name;
        }, $game->players));
    }

}