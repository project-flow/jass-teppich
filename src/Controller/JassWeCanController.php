<?php

namespace App\Controller;

use App\Repository;
use function Jass\CardSet\jassSet;
use Jass\Message\Deal;
use Jass\Message\PlayerSetup;
use function Jass\Player\byNames;
use Jass\Strategy\Bock;
use Jass\Strategy\Simple;
use Jass\Strategy\TeamMate;
use Jass\Strategy\Trump;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;

class JassWeCanController extends Controller
{
    /**
     * @Route("/jwc", name="create")
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
}
