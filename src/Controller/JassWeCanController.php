<?php

namespace App\Controller;

use App\Repository;
use function Jass\CardSet\jassSet;
use Jass\Entity\Card;
use Jass\Entity\Trick;
use function Jass\Game\isFinished;
use function Jass\Game\isReady;
use function Jass\Game\teamPoints;
use function Jass\Game\teams;
use function Jass\Hand\last;
use function Jass\Hand\ordered;
use Jass\Message\Deal;
use Jass\Message\PlayerSetup;
use Jass\Message\StyleSetup;
use Jass\Message\Turn;
use Jass\MessageHandler;
use function Jass\Player\byNames;
use Jass\Strategy\Bock;
use function Jass\Strategy\cardStrategy;
use Jass\Strategy\Simple;
use Jass\Strategy\TeamMate;
use Jass\Strategy\Trump;
use Jass\Style\BottomUp;
use Jass\Style\TopDown;
use Jass\Style\Trump as TrumpStyle;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/jwc")
 */
class JassWeCanController extends AbstractController
{
    /**
     * @Route("/", name="jwc_create", methods={"POST"})
     * @param Repository $repository
     * @return JsonResponse
     */
    public function create(Repository $repository)
    {
        $name = md5(uniqid());

        $playerSetup = new PlayerSetup();

        $playerSetup->players = byNames('Ueli, Fritz, Hans, Peter');
        $playerSetup->starter = $playerSetup->players[0];

        foreach ($playerSetup->players as $player) {
            $player->strategies = [
                Trump::class,
                Bock::class,
                TeamMate::class,
                Simple::class
            ];
        }

        $repository->recordMessage($name, $playerSetup);

        $deal = new Deal();
        $cards = jassSet();
        shuffle($cards);
        $deal->cards = $cards;

        $repository->recordMessage($name, $deal);

        return $this->info($name, $repository);
    }

    /**
     * @Route("/{id}", name="jwc_info", methods={"POST"})
     * @param string $id
     * @param Repository $repository
     * @return JsonResponse
     */
    public function info(string $id, Repository $repository)
    {
        $game = $repository->loadGame($id);

        $orderFunction = $game->style ? $game->style->orderFunction() : (new TopDown())->orderFunction();

        $teams = [];
        foreach (teams($game) as $team) {
            $teams[] = [
                'name' => $team,
                'points' => teamPoints($team, $game)
            ];
        }

        $data = [
            'id' => $game->name,
            'hand' => array_reverse(ordered($game->players[0]->hand, $orderFunction)),
            'ready' => isReady($game),
            'trickNumber' => count($game->playedTricks) + 1,
            'teams' => $teams,
        ];

        if ($game->style) {
            $data['style'] = $game->style->name;
        }

        $data['trick'] = $this->trickCards($game->currentTrick, $game->players);

        return new JsonResponse($data, 200, ['Access-Control-Allow-Origin' => '*', 'Access-Control-Allow-Headers' => 'content-type']);
    }

    private function trickCards(?Trick $trick, $players) {
        $trickCards = [null, null, null, null];
        if ($trick) {
            foreach ($trick->turns as $turn) {
                $index = array_search($turn->player, $players);
                $trickCards[$index] = $turn->card;
            }
        }
        return $trickCards;
    }

    /**
     * @Route("/{id}", name="jwc_play", methods={"POST"})
     * @param string $id
     * @param Request $request
     * @param Repository $repository
     * @return JsonResponse
     */
    public function play(string $id, Request $request, Repository $repository)
    {
        if ($request->getMethod() === 'OPTIONS') {
            return $this->info($id, $repository);
        }

        $input = json_decode($request->getContent(), true);
        $messageHandler = new MessageHandler();
        $game = $repository->loadGame($id);

        $card = Card::from($input['card']['suit'], $input['card']['value']);

        $playCard = new Turn();
        $playCard->card = $card;

        $playedTricks = count($game->playedTricks);

        $game = $messageHandler->handle($game, $playCard);
        $repository->recordMessage($id, $playCard);

        while (!isFinished($game) && $game->currentPlayer !== $game->players[0]) {
            $turn = new Turn();
            $turn->card = \Jass\Strategy\card($game);
            $messageHandler->handle($game, $turn);
            $repository->recordMessage($id, $turn);
        }

        $data = [];
        $data['trick'] = $this->trickCards($game->currentTrick, $game->players);

        if ($playedTricks !== count($game->playedTricks)) {
            /** @var Trick $lastTrick */
            $lastTrick = last($game->playedTricks);
            $data['lastTrick'] = $this->trickCards($lastTrick, $game->players);
        }

        if (!isFinished($game)) {
            $strategy = cardStrategy($game);
            $data['hint'] = $strategy->chooseCard($game);
            $data['hintReason'] = get_class($strategy);
        }

        $teams = [];
        foreach (teams($game) as $team) {
            $teams[] = [
                'name' => $team,
                'points' => teamPoints($team, $game)
            ];
        }
        $data['teams'] = $teams;


        return new JsonResponse($data, 200, ['Access-Control-Allow-Origin' => '*', 'Access-Control-Allow-Headers' => 'content-type']);
    }

    /**
     * @Route("/{id}/style", name="jwc_style")
     * @param string $id
     * @param Request $request
     * @param Repository $repository
     * @return JsonResponse
     */
    public function style(string $id, Request $request, Repository $repository)
    {
        if ($request->getMethod() === 'OPTIONS') {
            return $this->info($id, $repository);
        }

        $input = json_decode($request->getContent(), true);

        list($first, $second) = explode(' ', $input['style']);
        if ($first === 'trump') {
            $style = new TrumpStyle($second);
        } elseif ($first === 'top') {
            $style = new TopDown();
        } else {
            $style = new BottomUp();
        }

        $styleSetup = new StyleSetup();
        $styleSetup->style = $style;

        $repository->recordMessage($id, $styleSetup);

        return $this->info($id, $repository);
    }
}
