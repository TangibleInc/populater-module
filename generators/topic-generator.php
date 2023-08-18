<?php

// This file is for functions specific to topics.
function get_topic_prefix() {
  return 'topic-';
};

function add_topic_to_lesson( $topic_name, $lesson_name, $course_name ) {
    global $wpdb;
    $topicid = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $topic_name . "'"  );
    $lessonid = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $lesson_name . "'"  );
    $courseid = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $course_name . "'"  );
    update_post_meta( $topicid, 'course_id', $courseid  );
    update_post_meta( $topicid, 'lesson_id', $lessonid  );
    update_post_meta( $topicid, '_sfwd-topic', [0, "swfd-topic_course" => $courseid, "sfwd-topic_lesson" => $lessonid ] );
};

function generate_topic( $topic_name, $course_iteration_flag, $lesson_iteration_flag ) {
    $fields = tangible_fields();
    $faker = Faker\Factory::create();
    
    wp_insert_post (
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

    $course_name = get_course_prefix() . $course_iteration_flag;
    $lesson_name = get_lesson_prefix() . $lesson_iteration_flag;

    add_topic_to_lesson( $topic_name, $lesson_name, $course_name );
    return true;
};

function remove_topic( $number ): void {
  global $wpdb;
  $topic_name = get_topic_prefix() . $number;
  $postid = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $topic_name . "'" );
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
