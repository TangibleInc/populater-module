<?php

defined('ABSPATH') or die();

if ( ! function_exists( 'populater' ) ) :
  function populater( $arg = false ) {
    static $o;
    return $arg === false ? $o : ( $o = $arg );
  }
endif;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/vendor/tangible/fields/index.php';

new class extends stdClass {

  public $name = 'populater';

  // Remember to update the version - Expected format: YYYYMMDD
  public $version = '20230818';

  function __construct() {

    $name     = $this->name;
    $priority = 99999999 - absint( $this->version );

    remove_all_filters( $name, $priority );
    add_action( $name, [ $this, 'load' ], $priority );

    $ensure_action = function() use ( $name ) {
      if ( ! did_action( $name )) do_action( $name );
    };

    add_action('plugins_loaded', $ensure_action, 0);
    add_action('after_setup_theme', $ensure_action, 0);
  }

  function load() {

    remove_all_filters( $this->name ); // First one to load wins

    populater( $this );

    $populater = $this;

    $fields = tangible_fields();

    // Load module features
    require_once __DIR__ . '/generators/index.php';
  }
};
