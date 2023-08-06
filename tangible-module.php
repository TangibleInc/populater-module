<?php

if ( ! function_exists( 'tangible_module_name' ) ) :
  function tangible_module_name( $arg = false ) {
    static $o;
    return $arg === false ? $o : ( $o = $arg );
  }
endif;

new class {

  public $name = 'tangible_module_name';

  // Remember to update the version - Expected format: YYYYMMDD
  public $version = '20221013';

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

    tangible_module_name( $this );

    $module = $this;

    // Load module features
    require_once __DIR__.'/feature.php';
  }
};