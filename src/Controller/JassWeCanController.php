<?php

namespace App\Controller;

use App\Repository;
use function Jass\CardSet\jassSet;
use Jass\Entity\Card;
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
use function Jass\Trick\playedCards;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Route("/jwc")
 */
class JassWeCanController extends Controller
{
    /**
     * @Route("/", name="create")
     * @Method({"POST"})
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

        $game = $repository->loadGame($name);

        $data = [
            'id' => $game->name,
            'hand' => $game->players[0]->hand
        ];

        return new JsonResponse($data);
    }

    /**
     * @Route("/{id}", name="info")
     * @Method({"GET"})
     * @param string $id
     * @param Repository $repository
     * @return JsonResponse
     */
    public function info(string $id, Repository $repository)
    {
        $game = $repository->loadGame($id);

        $data = [
            'id' => $game->name,
            'hand' => $game->players[0]->hand
        ];

        if ($game->style) {
            $data['style'] = $game->style->name;
        }

        if ($game->currentTrick) {
            $data['trick'] = playedCards($game->currentTrick);
        }

        return new JsonResponse($data);
    }

    /**
     * @Route("/{id}", name="play")
     * @Method({"POST"})
     * @param string $id
     * @param Request $request
     * @param Repository $repository
     * @return JsonResponse
     */
    public function play(string $id, Request $request, Repository $repository)
    {
        $input = json_decode($request->getContent(), true);

        $card = Card::from($input['card']['suit'], $input['card']['value']);

        $playCard = new Turn();
        $playCard->card = $card;

        $repository->recordMessage($id, $playCard);
        $game = $repository->loadGame($id);


        $messageHandler = new MessageHandler();
        $playedCards = [];
        do {
            $turn = new Turn();
            $turn->card = \Jass\Strategy\card($game);
            $messageHandler->handle($game, $turn);
            $repository->recordMessage($id, $turn);
            $playedCards[] = $turn->card;
        } while ($game->currentPlayer !== $game->players[0]);

        $strategy = cardStrategy($game);

        $data = [
            'playedCards' => $playedCards,
            'hint' => $strategy->chooseCard($game),
            'hintReason' => get_class($strategy),
        ];

        return new JsonResponse($data);
    }

    /**
     * @Route("/{id}/style", name="style")
     * @Method({"POST"})
     * @param string $id
     * @param Request $request
     * @param Repository $repository
     * @return JsonResponse
     */
    public function style(string $id, Request $request, Repository $repository)
    {
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
