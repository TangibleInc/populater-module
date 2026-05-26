<?php

// This file is for functions specific to topics.
function get_topic_prefix() {
  return 'topic-';
};

function add_topic_to_lesson( $topic_id, $lesson_id, $course_id ) {
    update_post_meta( $topic_id, 'course_id', $course_id  );
    update_post_meta( $topic_id, 'lesson_id', $lesson_id  );
    update_post_meta( $topic_id, '_sfwd-topic', [0, "swfd-topic_course" => $course_id, "sfwd-topic_lesson" => $lesson_id ] );
};

function add_topic_to_course_steps( $topic_id, $lesson_id, $course_id ) {
  // Get the existing course steps
  $course_steps = get_post_meta($course_id, 'ld_course_steps', true);
  $course_steps['steps']['h']['sfwd-lessons'][$lesson_id]['sfwd-topic'] += [
    $topic_id => [
      'sfwd-quiz' => []
    ]
  ];

  update_post_meta($course_id, 'ld_course_steps', $course_steps);
};

function generate_topic( $topic_name, $course_iteration_flag, $lesson_iteration_flag, $parent_post ) {
    
    global $wpdb;
    $fields = tangible_fields();
    $faker = Faker\Factory::create();
    
    $topic_id = wp_insert_post (
      [
        'post_date'         => $faker->date( 'Y-m-d' ) . ' ' . $faker->time(),
        'post_date_gmt'     => $faker->date( 'Y-m-d' ) . ' ' . $faker->time(),
        'post_content'      => $faker->randomHtml(),
        'post_title'        => $topic_name,
        'post_excerpt'      => $faker->sentence(),
        'post_status'       => 'publish',
        'post_type'         => 'sfwd-topic',
        'post_name'         => $topic_name, 
      ]
    );

    if ( $parent_post === false ) return get_post($topic_id);

    $lesson_name = get_lesson_prefix() . $lesson_iteration_flag;

    if ( empty($parent_post) ) $parent_post = $lesson_name;
    if ( !is_numeric($parent_post) ) $parent_post = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $parent_post . "'"  );
    $course_id = get_post_meta( $parent_post, 'course_id',  true );

    add_topic_to_lesson( $topic_id, $parent_post, $course_id );
    add_topic_to_course_steps( $topic_id, $parent_post, $course_id );
    return get_post($topic_id);
};

function remove_topic( $number ): void {
  global $wpdb;
  $topic_name = get_topic_prefix() . $number;
  $postid = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $topic_name . "'" );
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
