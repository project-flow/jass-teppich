<?php

namespace App\Command;

use App\Repository;
use Jass\Message\StyleSetup;
use Jass\MessageHandler;
use Jass\Strategy\Azeige;
use Jass\Strategy\Bock;
use Jass\Strategy\Simple;
use Jass\Strategy\TeamOnlySuits;
use Jass\Strategy\Verrueren;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Style extends Command
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
        $this->setName('jass:style')->setDescription('Sets the style of the game');
        $this->addArgument('game', InputArgument::REQUIRED, 'Name of the game');
        $this->addArgument('style', InputArgument::OPTIONAL, 'Style of the game', 'topDown');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $gameName = $input->getArgument('game');
        $game = $this->repository->loadGame($gameName);

        $styleName = $input->getArgument('style');

        $className = 'Jass\\Style\\' . ucfirst($styleName);
        if (!class_exists($className)) {
            $output->writeln('Unknown style ' . $styleName);
            return;
        }

        $strategies = [
            Verrueren::class,
            Azeige::class,
            Bock::class,
            TeamOnlySuits::class,
            Simple::class
        ];

        $message = new StyleSetup();
        $message->style = new $className();
        $message->strategies = [
            $strategies,
            [Simple::class],
            $strategies,
            [Simple::class]
        ];

        $this->repository->recordMessage($gameName, $message);

        $messageHandler = new MessageHandler();
        $messageHandler->handle($game, $message);

        $output->writeln('Style ' . $styleName . ' is set for game ' . $gameName);

    }

}