<?php


namespace App\Controller;


use App\Repository;
use function Jass\CardSet\bySuitsAndValues;
use function Jass\CardSet\suits;
use function Jass\CardSet\values;
use Jass\Entity\Card;
use Jass\Entity\Card\Suit;
use Jass\Entity\Trick;
use function Jass\Game\teamPoints;
use function Jass\Hand\last;
use function Jass\Hand\ordered;
use Jass\Message\Deal;
use Jass\Message\PlayerSetup;
use Jass\Message\StyleSetup;
use Jass\Message\Turn;
use Jass\MessageHandler;
use function Jass\Player\byNames;
use Jass\Strategy\Bock;
use Jass\Strategy\Simple;
use Jass\Strategy\TeamMate;
use Jass\Style\BottomUp;
use Jass\Style\TopDown;
use Jass\Style\Trump;
use function Jass\Trick\isFinished;
use function Jass\Trick\playedCards;
use function Jass\Trick\playerTurn;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use function Jass\Trick\winningTurn;

class GameController extends AbstractController
{
    /**
     * @Route("/", name="index")
     */
    public function index()
    {
        $id = md5(uniqid());
        return $this->redirectToRoute("game", ['id' => $id]);
    }

    /**
     * @Route("/game/{id}/style/{style}", name="style")
     */
    public function style($id, $style, Repository $repository)
    {
        $messageHandler = new MessageHandler();
        $game = $repository->loadGame($id);
        $message = new StyleSetup();
        switch ($style) {
            case 'top':
                $message->style = new TopDown();
                break;
            case 'bottom':
                $message->style = new BottomUp();
                break;
            case 'bell':
                $message->style = new Trump(Suit::BELL);
                break;
            case 'rose':
                $message->style = new Trump(Suit::ROSE);
                break;
            case 'oak':
                $message->style = new Trump(Suit::OAK);
                break;
            case 'shield':
                $message->style = new Trump(Suit::SHIELD);
                break;
            default:
                throw $this->createAccessDeniedException('Unknown style: ' . $style);
        }

        $strategies = [
            \Jass\Strategy\Trump::class,
            Bock::class,
            TeamMate::class,
            Simple::class
        ];

        // strategies per player
        $message->strategies = [
            $strategies,
            $strategies,
            $strategies,
            $strategies
        ];

        $messageHandler->handle($game, $message);
        $repository->recordMessage($id, $message);

        return $this->redirectToRoute("game", ['id' => $id]);
    }

    /**
     * @Route("/game/{id}/play/{card}", name="play")
     */
    public function play($id, $card, Repository $repository)
    {
        $messageHandler = new MessageHandler();
        $game = $repository->loadGame($id);

        $turn = new Turn();
        $turn->card = Card::shortcut($card);

        $messageHandler->handle($game, $turn);
        $repository->recordMessage($id, $turn);

        return $this->redirectToRoute("trick", ['id' => $id]);
    }

    /**
     * @Route("/game/{id}/trick", name="trick")
     */
    public function trick($id, Repository $repository)
    {
        $messageHandler = new MessageHandler();
        $game = $repository->loadGame($id);

        while ($game->currentTrick && !isFinished($game->currentTrick)) {
            $turn = new Turn();
            $turn->card = \Jass\Strategy\card($game);

            $messageHandler->handle($game, $turn);
            $repository->recordMessage($id, $turn);
        }

        $trick = last($game->playedTricks);
        $winningTurn = winningTurn($trick, $game->style->orderFunction());
        $trickInfo = [
            'points' => array_sum(array_map(function (Card $card) use ($game) {
                return $game->style->points($card);
            }, playedCards($trick))),
            'winner' => $winningTurn->player,
            'winningCard' => $winningTurn->card
        ];

        $cards = [];
        foreach ($game->players as $player) {
            $turn = playerTurn($trick, $player);
            if ($turn) {
                $cards[] = $turn->card;
            } else {
                $cards[] = '&nbsp;';
            }
        }

        $info = [
            'style' => ucwords($game->style->name),
            'trickNumber' => $game->playedTricks ? count($game->playedTricks) : 1,
            'team1points' => teamPoints($game->players[0]->team, $game),
            'team2points' => teamPoints($game->players[1]->team, $game),
            'finished' => \Jass\Game\isFinished($game)
        ];

        return $this->render('Game/index.html.twig', [
            'id' => $id,
            'trick' => $cards,
            'info' => $info,
            'style' => false,
            'trickInfo' => $trickInfo
        ]);
    }

    /**
     * @Route("/game/{id}", name="game")
     */
    public function game($id, Repository $repository)
    {
        $messageHandler = new MessageHandler();
        $game = $repository->loadGame($id);
        if (!$game->players) {
            // if no players are setup set the up
            $setup = new PlayerSetup();
            $setup->players = byNames('Franz,Heidi,Frieda,Hans');
            $setup->starter = $setup->players[0];

            $messageHandler->handle($game, $setup);
            $repository->recordMessage($id, $setup);
        }

        if (!$game->players[0]->hand && !\Jass\Game\isFinished($game)) {
            $cards = bySuitsAndValues(suits(), values());
            shuffle($cards);

            $message = new Deal();
            $message->cards = $cards;

            $messageHandler->handle($game, $message);
            $repository->recordMessage($id, $message);
        }

        if (!$game->style) {
            $topDown = new TopDown();
            return $this->render('Game/index.html.twig', [
                'id' => $id,
                'trick' => false,
                'info' => false,
                'style' => true,
                'hand' => ordered($game->players[0]->hand, $topDown->orderFunction())
            ]);
        }

        $info = [
            'style' => ucwords($game->style->name),
            'trickNumber' => $game->playedTricks ? count($game->playedTricks) + 1 : 1,
            'team1points' => teamPoints($game->players[0]->team, $game),
            'team2points' => teamPoints($game->players[1]->team, $game),
            'finished' => \Jass\Game\isFinished($game),
        ];

        if (\Jass\Game\isFinished($game)) {
            return $this->render('Game/index.html.twig', [
                'id' => $id,
                'trick' => false,
                'info' => $info,
                'style' => false,
                'hand' => []
            ]);
        }

        while ($game->currentPlayer !== $game->players[0]) {
            $turn = new Turn();
            $turn->card = \Jass\Strategy\card($game);

            $messageHandler->handle($game, $turn);
            $repository->recordMessage($id, $turn);
        }

        if ($game->currentTrick) {
            $trick = $game->currentTrick;
        } else {
            $trick = new Trick();
        }
        foreach ($game->players as $player) {
            $turn = playerTurn($trick, $player);
            if ($turn) {
                $cards[] = $turn->card;
            } else {
                $cards[] = '&nbsp;';
            }
        }

        return $this->render('Game/index.html.twig', [
            'id' => $id,
            'trick' => $cards,
            'info' => $info,
            'style' => false,
            'hand' => ordered($game->players[0]->hand, $game->style->orderFunction()),
            'hint' => \Jass\Strategy\card($game),
            'player' => $game->currentPlayer->name
        ]);
    }

}