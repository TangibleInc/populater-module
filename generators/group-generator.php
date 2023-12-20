<?php

// This file is for functions specific to groups. 
function get_group_prefix() {
  return 'group-';
};

function generate_group( $group_name ) {

    $faker = Faker\Factory::create();
    $fields = tangible_fields();
  
    $group_id = wp_insert_post(
      [
        'post_date'         => $faker->date( 'Y-m-d' ) . ' ' . $faker->time(),
        'post_date_gmt'     => $faker->date( 'Y-m-d' ) . ' ' . $faker->time(),
        'post_content'      => $faker->randomHtml(),
        'post_title'        => $group_name,
        'post_excerpt'      => $faker->sentence(),
        'post_status'       => 'publish',
        'post_type'         => 'groups',
        'post_name'         => $group_name, 
      ]
    );
  
    return get_post($group_id);
};

$populater->add_user_to_group = function($group_id, $user_id, $user_type = 'user') {

    $meta_key = 'learndash_group_users_'.$group_id;
    $meta_key_leader = 'learndash_group_leaders_'.$group_id;
    $learndash_group_users = empty(get_post_meta($group_id, $meta_key, true)) ? [] : get_post_meta($group_id, $meta_key, true);

    if ( empty($learndash_group_users) ) {
        $learndash_group_users[0] = $user_id;
        update_user_meta($user_id, $meta_key, $group_id);
        update_user_meta($user_id, $meta_key_leader, $group_id);
    } else {
        if ( !in_array($user_id, $learndash_group_users) ) $learndash_group_users []= $user_id;
        update_user_meta($user_id, $meta_key, $group_id);
    }

    update_post_meta($group_id, $meta_key, $learndash_group_users);
};
