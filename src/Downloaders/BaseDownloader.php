<?php
namespace TikScraper\Downloaders;
use TikScraper\Helpers\Tokens;
use TikScraper\Wrappers\Guzzle;

abstract class BaseDownloader {
    protected const BUFFER_SIZE = 1024;

    protected Tokens $tokens;
    protected Guzzle $guzzle;

    function __construct(array $config = []) {
        $this->tokens = new Tokens($config);
        $this->guzzle = new Guzzle($config);
    }
}