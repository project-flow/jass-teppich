<?php

namespace App\Command;

use App\Repository;
use function Jass\CardSet\bySuitsAndValues;
use function Jass\CardSet\suits;
use function Jass\CardSet\values;
use Jass\MessageHandler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Deal extends Command
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
        $this->setName('jass:deal')->setDescription('Deals cards to the players');
        $this->addArgument('game', InputArgument::REQUIRED, 'Name of the game');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $gameName = $input->getArgument('game');
        $game = $this->repository->loadGame($gameName);

        $cards = bySuitsAndValues(suits(), values());
        shuffle($cards);


        $message = new \Jass\Message\Deal();
        $message->cards = $cards;

        $this->repository->recordMessage($gameName, $message);

        $messageHandler = new MessageHandler();
        $messageHandler->handle($game, $message);

        $output->writeln('Cards dealt for game ' . $gameName);

    }

}