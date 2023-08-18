<?php

// This file is for functions specific to users. 
function get_user_prefix() {
    return 'user-';
};

function generate_user( $number ) {
    
    $password = 'password';
    $username = get_user_prefix() . $number;
    $create_user_success = wp_create_user( $username, $password ); // returns int on success and WP_Error on failure
    if(is_wp_error( $create_user_success ) ) return $create_user_success;

    $user = get_user_by( 'login', $username ); 
    $add_user_meta_success = add_user_meta($user->ID, 'fake_user', 'true' ); // returns meta ID on success and false on failure
    
    if(!$add_user_meta_success) return false;

    return true;
};

function remove_user( int $number ): void {

    $username = get_user_prefix() . $number;
    $user = get_user_by( 'login', $username );
    if( $user ) {
      $user_id = $user->ID;
      if( get_user_meta( $user_id, 'fake_user' ) ) {
        delete_metadata( 
          'user', 
          $user_id, 
          '', 
          true 
        );
        wp_delete_user( $user_id );
      }
    }
};
