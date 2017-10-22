<?php

namespace App\Command;


use function Jass\CardSet\jassSet;
use function Jass\Player\byNames;
use function Jass\Game\isFinished;
use function Jass\Game\teamPoints;
use function Jass\Game\teams;
use function Jass\Strategy\card;
use function Jass\Strategy\firstCardOfTrick;
use Jass\Entity\Game;
use Jass\Message\PlayerSetup;
use Jass\Message\StyleSetup;
use Jass\Message\Turn;
use Jass\MessageHandler;
use Jass\Strategy\Azeige;
use Jass\Strategy\Bock;
use Jass\Strategy\Simple;
use Jass\Strategy\TeamOnlySuits;
use Jass\Strategy\Verrueren;
use Jass\Style\TopDown;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Strategies extends Command
{
    protected function configure()
    {
        $this->setName('jass:strategies')->setDescription('Plays n games with two teams having different strategies');
        $this->addArgument('count', InputArgument::OPTIONAL, 'How many games should be played', 1000);
    }

    public function run(InputInterface $input, OutputInterface $output)
    {
        $start = microtime(true);
        $strategies = [
            Verrueren::class,
            Azeige::class,
            Bock::class,
            TeamOnlySuits::class,
            Simple::class
        ];

        $playerSetup = new PlayerSetup();
        $playerSetup->players = byNames('Smarty, Dumby, Smarty 2, Dumby 2');

        $playerSetup->players[0]->strategies = $strategies;
        $playerSetup->players[1]->strategies = [Simple::class];
        $playerSetup->players[2]->strategies = $strategies;
        $playerSetup->players[3]->strategies = [Simple::class];

        $styleSetup = new StyleSetup();
        $styleSetup->style = new TopDown();

        $count = $input->getArgument('count');

        $points = [];

        $messageHandler = new MessageHandler();

        $progress = new ProgressBar($output, $count);
        $progress->start();
        for ($i = 0; $i < $count; $i++) {
            $game = new Game();

            $starterIndex = $i % 4;
            $playerSetup->starter = $playerSetup->players[$starterIndex];

            $game = $messageHandler->handle($game, $playerSetup);
            $game = $messageHandler->handle($game, $styleSetup);

            $deck = jassSet();
            shuffle($deck);

            $deal = new \Jass\Message\Deal();
            $deal->cards = $deck;
            $game = $messageHandler->handle($game, $deal);

            do {
                if ($game->currentTrick) {
                    $card = card($game->currentPlayer, $game->currentTrick, $game->style);
                } else {
                    $card = firstCardOfTrick($game->currentPlayer, $game->style);
                }
                $message = new Turn();
                $message->card = $card;

                $game = $messageHandler->handle($game, $message);

            } while (!isFinished($game));

            foreach (teams($game) as $team) {
                $points[$team][] = teamPoints($team, $game);
            }

            $progress->advance();
        }

        $progress->finish();

        $output->writeln('');
        $output->writeln('Average points in ' . $count . ' games');
        foreach ($points as $team => $pointInGames) {
            $output->write($team);
            $output->write(': ');
            $output->writeln(intval(array_sum($pointInGames) / count($pointInGames)));
        }
        $end = microtime(true);

        $time = ($end - $start);
        $output->writeln('Runtime information: ' . (memory_get_peak_usage(true)/1024/1024) . ' MB in ' . $time . ' s');
    }
}