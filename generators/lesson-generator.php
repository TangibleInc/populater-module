<?php

// This file is for functions specific to lessons.
function get_lesson_prefix() {
  return 'lesson-';
};

function add_lesson_to_course( $lesson_id, $course_id_or_name ) {
  global $wpdb;
  if ( !is_numeric($course_id_or_name) ) $course_id_or_name = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $course_id_or_name . "'"  );
  update_post_meta( $lesson_id, 'course_id', $course_id_or_name  );
  update_post_meta( $lesson_id, '_swfd-lessons', [0, "swfd-lessons_course" => $course_id_or_name] );
};

function add_lesson_to_course_steps( $lesson_id, $course_id_or_name ) {
  global $wpdb;
  if ( !is_numeric($course_id_or_name) ) $course_id_or_name = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $course_id_or_name . "'"  );

  // Get the existing course steps
  $course_steps = get_post_meta($course_id_or_name, 'ld_course_steps', true);
  $course_steps['steps']['h']['sfwd-lessons'] += [
    $lesson_id => [
      'sfwd-topic' => [],
      'sfwd-quiz' => []
    ]
  ];

  update_post_meta($course_id_or_name, 'ld_course_steps', $course_steps);
};

function generate_lesson( $lesson_name, $course_iteration_flag, $parent_post ) {

  $fields = tangible_fields();
  $faker = Faker\Factory::create();

  $lesson_id = wp_insert_post (
    [
      'post_date'         => $faker->date( 'Y_m_d' ) . $faker->time(),
      'post_date_gmt'     => $faker->date( 'Y_m_d' ) . $faker->time(),
      'post_content'      => $faker->randomHtml(),
      'post_title'        => $lesson_name,
      'post_excerpt'      => $faker->sentence(),
      'post_status'       => 'publish',
      'post_type'         => 'sfwd-lessons',
      'post_name'         => $lesson_name, 
    ]
  );

  $course_name = get_course_prefix() . $course_iteration_flag;
  if ( empty($parent_post) ) $parent_post = $course_name;

  add_lesson_to_course( $lesson_id, $parent_post );
  add_lesson_to_course_steps( $lesson_id, $parent_post );
  return get_post($lesson_id);
};

function remove_lesson( $number ) {
  global $wpdb;
  $lesson_name = get_lesson_prefix() . $number;
  $postid = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $lesson_name . "'" );
  if( $postid ) {
    delete_metadata( 
      'post', 
      $postid, 
      '',
      true 
    );
    wp_delete_post( $postid );
  }
};
