<?php

// This file is for functions that don't concern specific post or to generate posts.

$populater->check_generated_name_exists = function ( $name ) use ( $populater ) {

    global $wpdb;
    $postid = $wpdb->get_var( 
      "SELECT id 
       FROM $wpdb->posts 
       WHERE post_title = '" . $name . "'" 
    );

    return $postid ? true : false;
};

function get_fetch_value_name( $generate_type ) {

    $fetch_value_name = '';

    switch ( $generate_type ) {
        case 'course':
            $fetch_value_name = 'num_of_courses';
            break;

        case 'lesson':
            $fetch_value_name = 'num_of_lessons';
            break;

        case 'topic':
            $fetch_value_name = 'num_of_topics';
            break;

        case 'quiz':
            $fetch_value_name = 'num_of_quizzes';
            break;

        case 'question':
            $fetch_value_name = 'num_of_questions';
            break;

        case 'user':
            $fetch_value_name = 'num_of_users';
            break;
        
        default:
            break;
    }

    return $fetch_value_name;
}

function get_remove_functions( $generate_type, $number ) {

    switch ($generate_type) {
        case 'course':
            remove_course( $number );
            break;

        case 'lesson':
            remove_lesson( $number );
            break;

        case 'topic':
            remove_topic( $number );
            break;
        
        case 'quiz':
            remove_quiz( $number );
            break;

        case 'question':
            remove_question( $number );
            break;

        case 'user':
            remove_user( $number );
            break;
        
        default:
            break;
    }
};

$populater->remove_generated = function ( $generate_type ) {
    $fields = tangible_fields();
    $fetch_value_name = get_fetch_value_name( $generate_type );

    if ( empty($fetch_value_name) ) return false;

    if( null == $fields->fetch_value( $fetch_value_name ) ) {
      $fields->store_value( $fetch_value_name, 0 );
      return;
    } else {
      $num_of_generated = $fields->fetch_value( $fetch_value_name );
    }

    for( $i=0; $i < $num_of_generated; $i++ ) {
        get_remove_functions( $fetch_value_name, ( $i + 1 ) );
    }

    $fields->store_value( $fetch_value_name, 0);
};

$populater->get_all_posts = function ( $column_name = '*', $post_type ) {
    global $wpdb;
    $post_info = $wpdb->get_results("SELECT $column_name FROM $wpdb->posts WHERE post_type = '$post_type'", ARRAY_A);
    return $post_info;
};

$populater->get_post_id = function ( $post_name, $post_type ) {
    global $wpdb;
    $post_id = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '$post_name' AND post_type='$post_type'");
    return (int) $post_id ?? 0;
};

$populater->get_all_users = function ( $column_name = "*" ) {
    global $wpdb;
    $users_info = $wpdb->get_results("SELECT $column_name FROM $wpdb->users", ARRAY_A);
    return $users_info;
};

$populater->get_user_id = function ( $username ) {
    $user = get_user_by('login', $username);
    return (int) $user->ID ?? 0;
};
