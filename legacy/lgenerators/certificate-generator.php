<?php

function generate_certificate( $certificate_name, $course_iteration_flag, $quiz_iteration_flag, $parent_post ) {

    $faker = Faker\Factory::create();
    $fields = tangible_fields();

    $certificate_id = wp_insert_post(
        [
          'post_date'         => $faker->date( 'Y-m-d' ) . ' ' . $faker->time(),
          'post_date_gmt'     => $faker->date( 'Y-m-d' ) . ' ' . $faker->time(),
          'post_content'      => $faker->randomHtml(),
          'post_title'        => $certificate_name,
          'post_excerpt'      => $faker->sentence(),
          'post_status'       => 'publish',
          'post_type'         => 'sfwd-certificates',
          'post_name'         => $certificate_name, 
        ]
    );

    if ( $parent_post === false ) return get_post($certificate_id); 

    if ( empty($parent_post) || !isset($parent_post['type']) || $parent_post['type'] === 'course' ) {
      $course_name = get_course_prefix() . $course_iteration_flag;
      if ( !isset($parent_post['name_or_id']) ) {
        if ( empty($parent_post) ) $parent_post = $course_name;
      } else {
        $parent_post = $parent_post['name_or_id'];
      }
      add_certificate_to_course( $parent_post, $certificate_id );
    }

    if ( isset($parent_post['type']) && $parent_post['type'] === 'quiz' ) {
      $quiz_name = get_quiz_prefix() . $quiz_iteration_flag;
      if ( !isset($parent_post['name_or_id']) ) {
        $parent_post = $quiz_name;
      } else {
        $parent_post = $parent_post['name_or_id'];
      }
      add_certificate_to_quiz( $parent_post, $certificate_id );
    }

    return get_post($certificate_id);
};

function add_certificate_to_course( $course_id_or_name, $certificate_id ) {

  global $wpdb;
  if ( !is_numeric($course_id_or_name) ) $course_id_or_name = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $course_id_or_name . "'"  );

  update_post_meta( $course_id_or_name, '_sfwd-courses', [0, 'sfwd-courses_certificate' => $certificate_id] );
};

function add_certificate_to_quiz( $quiz_id_or_name, $certificate_id ) {

  global $wpdb;
  if ( !is_numeric($quiz_id_or_name) ) $quiz_id_or_name = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $quiz_id_or_name . "'"  );

  $previous_post_meta = get_post_meta( $quiz_id_or_name, '_sfwd-quiz', true );
  $previous_post_meta['sfwd-quiz_certificate'] = $certificate_id;
  update_post_meta( $quiz_id_or_name, '_sfwd-quiz', $previous_post_meta );
};
