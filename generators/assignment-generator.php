<?php

function add_assignment_to_lesson( $assignment_id, $course_id, $lesson_id, $username ) {

  $assignment = get_post($assignment_id);
  $lesson = get_post($lesson_id);
  $user_id = get_user_by('login', $username)->ID;
  $file_path = wp_upload_dir()['basedir'] . '/assignments/';

  update_post_meta( $lesson_id, 'sfwd_lessons-assignment', [] );

  $assignment_meta = array(
		'file_name'    => $assignment->post_title,
		'file_link'    => get_site_url() . '/wp-content/uploads/assignments/' . $assignment->post_title . '.jpg',
		'user_name'    => $username,
		'disp_name'    => $username, // cspell:disable-line.
		'file_path'    => rawurlencode( $file_path . $assignment->post_title ),
		'user_id'      => $user_id,
		'lesson_id'    => $lesson_id,
		'course_id'    => $course_id,
		'lesson_title' => $lesson->post_title,
		'lesson_type'  => $lesson->post_type
	);

  $points_enabled = learndash_get_setting( $lesson, 'lesson_assignment_points_enabled' );

  if ( 'on' === $points_enabled ) {
		$assignment_meta['points'] = 'pending';
	}

  $auto_approve = learndash_get_setting( $lesson, 'auto_approve_assignment' );

  foreach ( $assignment_meta as $key => $value ) {
    update_post_meta( $assignment_id, $key, $value );
  }
  do_action( 'learndash_assignment_uploaded', $assignment_id, $assignment_meta );

  if ( ! empty( $auto_approve ) ) {
		learndash_approve_assignment( $user_id, $lesson_id, $assignment_id );

		// assign full points if auto approve & points are enabled.
		if ( 'on' === $points_enabled ) {
			$points = learndash_get_setting( $lesson, 'lesson_assignment_points_amount' );
			update_post_meta( $assignment_id, 'points', intval( $points ) );
		}
	}

};

function generate_assignment( $assignment_name, $lesson_iteration_flag, $user_iteration_flag, $parent_post ) {

    global $wpdb;
    $faker = Faker\Factory::create();
    $fields = tangible_fields();
    $username = get_user_prefix() . $user_iteration_flag;

    $assignment_id = wp_insert_post(
        [
          'post_author'       => get_user_by('login', $username)->ID,
          'post_date'         => $faker->date( 'Y-m-d' ) . ' ' . $faker->time(),
          'post_date_gmt'     => $faker->date( 'Y-m-d' ) . ' ' . $faker->time(),
          'post_content'      => '<a href="' . get_site_url() . '/wp-content/uploads/assignments/' . $assignment_name . '.jpg" target="_blank" rel="noopener">' . $assignment_name . '.jpg</a>',
          'post_title'        => $assignment_name,
          'post_excerpt'      => $faker->sentence(),
          'post_status'       => 'publish',
          'post_type'         => 'sfwd-assignment',
          'post_name'         => $assignment_name
        ]
    );

    if ( $parent_post === false ) return get_post($assignment_id);

    $lesson_name = get_lesson_prefix() . $lesson_iteration_flag;

    if ( empty($parent_post) ) $parent_post = $lesson_name;
    if ( !is_numeric($parent_post) ) $parent_post = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $parent_post . "'"  );
    $course_id = get_post_meta( $parent_post, 'course_id',  true );

    add_assignment_to_lesson($assignment_id, $course_id, $parent_post, $username);

    return get_post($assignment_id);

};

$populater->create_custom_image_locally = function ( $assignment_post_title, $file_path ) {

  $custom_image = imagecreatetruecolor(110,20);
  imagepng($custom_image, $file_path . $assignment_post_title);
};
