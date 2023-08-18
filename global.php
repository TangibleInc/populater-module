<?php

class Populater {
  public static $instance;
  public $state;

  function __construct() {
    self::$instance = $this;
  }
}

new Populater;

function popu() {
  return Populater::$instance;
}

popu()->state = [
  'version' => '20230806',
  'url' => plugins_url('/', __FILE__),
  'cache' => [],
];

include __DIR__.'/generators/index.php';
include __DIR__.'/vendor/autoload.php';
