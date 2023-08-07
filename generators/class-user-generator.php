<?php

declare(strict_types=1);
namespace Populater;

defined( 'ABSPATH' ) || exit;

use Tangible\Populater\AbstractGenerator;

// @link https://developer.wordpress.org/reference/functions/wp_delete_user/#more-information
require_once( ABSPATH.'wp-admin/includes/user.php' );

/**
*  Class User Generator
*/
class User_Generator implements AbstractGenerator { 
  private static $instance = null;
  private  $prefix = 'user-';
  private $password = 'password';
  static $plugin;
  
  private function __construct() {
    $prefix = 'user-';
  }

  public static function getInstance() {
    if(!self::$instance)
    {
      self::$instance = new User_Generator();
    }
    return self::$instance;
  }

  /**
   * function which generate users
   *
   * @param object $plugin
   * @return bool|\WP_Error 
   */
  function generate( int $num_of_users_to_add ): bool|\WP_Error {
    
    if( $num_of_users_to_add == 0 ) {
      return true;
    }
    if( $num_of_users_to_add < 0 ) {
      return new \WP_Error( 'user generation error', __( 'Input cannot be negative', 'tangible_populater' ) );
    }
    $fields = tangible_fields();
    $previous_num_of_users = $fields->fetch_value( 'num_of_users' );
    $total_num_of_users = $previous_num_of_users + $num_of_users_to_add;
    
    for( $added_users = 0; $added_users < $num_of_users_to_add; $added_users++ ) { 
      $username = $this->prefix . ( $previous_num_of_users + $added_users + 1 );
      if( username_exists( $username ) ) {
        for( $num_of_users_removed = 0; $num_of_users_removed <  $added_users; $num_of_users_removed++ ) {
          $this->remove_user( 
            $previous_num_of_users + $num_of_users_removed + 1 
          );
        }
        return new \WP_Error( 'user generation error', __('User already exists, please change the prefix and try again.') );
      } else {
        $this->create_fake_user( $username );
      }
    }

    $fields->store_value( 'num_of_users', $total_num_of_users );
    return true;
  }

  /**
   * function which removes all the generated users from the Database 
   *
   * @return void
   */
  function remove_generated(): void {  
    $fields = tangible_fields();

    if( null == $fields->fetch_value( 'num_of_users' ) ) {
      $fields->store_value( 'num_of_users', 0 );
      return;
    } else {
      $num_of_users = $fields->fetch_value( 'num_of_users' );
    }

    for( $i=0; $i < $num_of_users; $i++ ) {
      $this->remove_user( $i + 1 );
    }

    $fields->store_value( 'num_of_users', 0 );
  }

  /**
   * removes a user with it's prefix based on it's number
   *
   * @param int $number
   * @return void
   */
  function remove_user( int $number ): void {
    $user_name = $this->prefix . $number;
    $user = get_user_by( 'login', $user_name );
    if( $user ) {
      $user_id = $user->ID;
      if( get_user_meta( $user_id, 'fake_user' ) ) {
        delete_metadata( 
          meta_type:'user', 
          object_id: $user_id, 
          meta_key: '', 
          delete_all:true 
        );
      wp_delete_user( $user_id );
      }
    }
  }

  /**
   * Add user to database, adds user meta 'fake_user'
   *
   * @param [type] $username
   * @return bool
   * True on success, false on failure
   */
  function create_fake_user( string $username ): bool|\WP_Error {
    $create_user_success = wp_create_user( $username, $this->password ); // returns int on success and WP_Error on failure
    if(is_wp_error( $create_user_success ) ) {
      return $create_user_success;
    }
    $user = get_user_by( 'login', $username ); 
    $add_user_meta_success = add_user_meta($user->ID, 'fake_user', 'true' ); // returns meta ID on success and false on failure
    if(!$add_user_meta_success) {
      return new \WP_Error( 'add_user_meta_failed', __( 'Failed while trying to add user meta.', 'tangible_populater'));
    }
    return true;
  }

  function get_prefix() {
    return $this->prefix;
  }
}

User_Generator::$plugin = $plugin;
