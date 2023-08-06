<?php

require __DIR__ . '/tangible-module.php';

if ( ! function_exists( 'populater' ) ) :

function populater( $instance = null ) {
  static $o;
  if (is_a($instance, 'TangibleModule')) $o = $instance->latest;
  return $o;
}

endif;

return populater(new class extends TangibleModule {

  public $name    = 'populater';
  public $version = '20230806';
  public $url     = '';
  public $state   = [];

  function load_latest_version() {

    $populater = $this;

    /**
     * Global namespace, functions, shortcodes
     */
    if ( ! class_exists('Populater') ) {
      require_once __DIR__.'/global.php';
    }
  }
});
