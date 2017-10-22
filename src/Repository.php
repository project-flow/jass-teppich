<?php

namespace App;


use Jass\Entity\Game;
use Jass\Message\Message;
use Jass\MessageHandler;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class Repository
{
    private $rootDir;

    public function __construct(string $rootDir)
    {
        $this->rootDir = $rootDir;
    }


    public function loadGame(string $name, callable $before = null, callable $after = null) : Game
    {
        $game = new Game();
        $game->name = $name;
        $messages = $this->messages($name);
        if ($messages) {
            $handler = new MessageHandler();
            foreach ($messages as $step => $message) {
                if (!is_null($before)) {
                    if ($before($game, $message, $step) === false) {
                        break;
                    }
                }
                $game = $handler->handle($game, $message);
                if (!is_null($after)) {
                    if ($after($game, $message, $step) === false) {
                        break;
                    }
                }
            }
        }
        return $game;
    }

    public function recordMessage(string $name, Message $message)
    {
        $dir = $this->rootDir . '/' . $name;
        $fs = new Filesystem();
        if (!is_dir($dir)) {
            $fs->mkdir($dir);
        }

        $finder = new Finder();
        $number = str_pad($finder->files()->in($dir)->count(), 3 ,'0', STR_PAD_LEFT);
        $fileName = $dir . '/' . $number;

        $fs->dumpFile($fileName, serialize($message));
    }

    /**
     * @param string $name
     * @return Message[]
     */
    private function messages(string $name)
    {
        $dir = $this->rootDir . '/' . $name;
        if (!is_dir($dir)) {
            return [];
        }
        $finder = new Finder();
        $result = [];
        foreach ($finder->sortByName()->files()->in($dir) as $file) {
            /** @var SplFileInfo $file */
            $result[] = unserialize($file->getContents());
        }
        return $result;
    }
}