<?php

// This file is for functions specific to lessons.
function get_lesson_prefix() {
  return 'lesson-';
};

function add_lesson_to_course( $lesson_name, $course_name ) {
    global $wpdb;
    $courseid = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $course_name . "'"  );
    $lessonid = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $lesson_name . "'"  );
    update_post_meta( $lessonid, 'course_id', $courseid  );
    update_post_meta( $lessonid, '_swfd-lessons', [0, "swfd-lessons_course" => $courseid] );
};

function add_lesson_to_course_steps( $lesson_id, $course_name ) {
  global $wpdb;
  $course_id = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $course_name . "'"  );

  // Get the existing course steps
  $course_steps = get_post_meta($course_id, 'ld_course_steps', true);
  $course_steps['steps']['h']['sfwd-lessons'] += [
    $lesson_id => [
      'sfwd-topic' => [],
      'sfwd-quiz' => []
    ]
  ];

  update_post_meta($course_id, 'ld_course_steps', $course_steps);
};

function generate_lesson( $lesson_name, $course_iteration_flag ) {

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
    add_lesson_to_course( $lesson_name, $course_name );
    add_lesson_to_course_steps( $lesson_id, $course_name );
    return true;
};

function remove_lesson( $number ) {
  global $wpdb;
  $lesson_name = get_lesson_prefix() . $number;
  $postid = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $lesson_name . "'" );
  if( $postid ) {
    delete_metadata( 
      meta_type:'post', 
      object_id: $postid, 
      meta_key: '', 
      delete_all:true 
    );
    wp_delete_post( $postid );
  }
};
