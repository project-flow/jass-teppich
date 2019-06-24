<?php


namespace App;


use Jass\Entity\Card;
use Jass\Entity\Player;
use function Jass\Hand\ordered;
use function Jass\Game\testGame;

class Handler
{
    /** @var \Jass\Entity\Game */
    public $game;

    public function __construct()
    {
        $this->game = testGame();
    }

    public function teams()
    {
        $teams = array_map(function(Player $player) {
            return $player->team;
        }, $this->game->players);
        return array_unique($teams);
    }

    public function players()
    {
        return array_map(function(Player $player) {
            return (string) $player;
        }, $this->game->players);
    }

    public function hand() {
        return array_map(function(Card $card) {
            return (string) $card;
        }, ordered($this->game->currentPlayer->hand, $this->game->style->orderFunction()));
    }

    public function redeal()
    {
        $this->game = testGame();
        return $this->hand();
    }
}