<?php

// This file is for functions specific to courses. 
function get_course_prefix() {
  return 'course-';
};

function initialize_course_steps( $course_id ) {
  $course_steps =
    [
      'steps' => [
        'h' => [
          'sfwd-lessons' => [],
          'sfwd-quiz'    => []
        ]
      ],
      'course_id' => $course_id,
      'version' => '4.7.0.2',
      'empty' => '',
      'course_builder_enabled' => 1,
      'course_shared_steps_enabled' => '',
      'steps_count' => 1,
    ];

  update_post_meta($course_id, 'ld_course_steps', $course_steps);
}

function generate_course( $course_name ) {

  $faker = Faker\Factory::create();
  $fields = tangible_fields();

  $course_id = wp_insert_post(
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

  initialize_course_steps($course_id);
  return get_post($course_id);
};

function remove_course( $number ) {
  global $wpdb;
  $course_name = get_course_prefix() . $number;
  $postid = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $course_name . "'" );
  if( $postid ) wp_delete_post( $postid );
};

$populater->add_section = function ( $course_id, $order, $post_title ) {

  $sections = json_decode(get_post_meta( $course_id, 'course_sections', true ));
  if ( empty($sections) ) $sections = [];

  array_push($sections, (object) [
    'order'       => $order,
    'ID'          => (int) round(microtime(true) * 1000),
    'post_title'  => $post_title,
    'url'         => '',
    'edit_link'   => '',
    'tree'        => [],
    'expanded'    => '',
    'type'        => 'section-heading'
  ] );

  update_post_meta( $course_id, 'course_sections', json_encode($sections) );
};

$populater->set_course_status = function($course_status, $user_id, $course_id) {

  switch ( $course_status ) {

    case 'locked' :
      update_post_meta( $course_id, '_sfwd-courses', ["sfwd-courses_course_price_type" => 'closed']);
      break;

    case 'open' :
      update_post_meta( $course_id, '_sfwd-courses', ["sfwd-courses_course_price_type" => 'open']);
      break;

    case 'started' :
      $lessons_list = learndash_get_course_lessons_list($course_id, $user_id);
      learndash_activity_start_course($user_id, $course_id, time());
      learndash_activity_start_lesson($user_id, $course_id, $lessons_list[1]['id'], time());
      learndash_activity_complete_lesson($user_id, $course_id, $lessons_list[1]['id'], time());
      learndash_process_mark_complete($user_id, $lessons_list[1]['id']);
      learndash_activity_start_lesson($user_id, $course_id, $lessons_list[2]['id'], time());
      break;

    case 'completed' :
      $lessons_list = learndash_get_course_lessons_list($course_id, $user_id);
      learndash_activity_start_course($user_id, $course_id, time());
      foreach ($lessons_list as $key => $lesson) {
        learndash_activity_start_lesson($user_id, $course_id, $lesson['id'], time());
        learndash_activity_complete_lesson($user_id, $course_id, $lesson['id'], time());
        learndash_process_mark_complete($user_id, $lesson['id']);
      }
      learndash_process_mark_complete($user_id, $course_id);
      break;

    default : 
      break;

  }
};
