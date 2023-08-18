<?php

// This file is for functions specific to courses. 
function get_course_prefix() {
  return 'course-';
};

function generate_course( $course_name ) {

  $faker = Faker\Factory::create();
  $fields = tangible_fields();

  wp_insert_post(
    [
      'post_date'         => $faker->date( 'Y-m-d' ) . ' ' . $faker->time(),
      'post_date_gmt'     => $faker->date( 'Y-m-d' ) . ' ' . $faker->time(),
      'post_content'      => $faker->randomHtml(),
      'post_title'        => $course_name,
      'post_excerpt'      => $faker->sentence(),
      'post_status'       => 'publish',
      'post_type'         => 'sfwd-courses',
      'post_name'         => $course_name, 
    ]
  );

  return true;
};

function remove_course( $number ) {
  global $wpdb;
  $course_name = get_course_prefix() . $number;
  $postid = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $course_name . "'" );
  if( $postid ) wp_delete_post( $postid );
};
