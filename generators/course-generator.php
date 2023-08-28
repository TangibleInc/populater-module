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

  array_push($sections, (object) [
    'order'       => $order,
    'ID'          => (int) time(),
    'post_title'  => $post_title,
    'url'         => '',
    'edit_link'   => '',
    'tree'        => [],
    'expanded'    => '',
    'type'        => 'section-heading'
  ] );

  update_post_meta( $course_id, 'course_sections', json_encode($sections) );
};
