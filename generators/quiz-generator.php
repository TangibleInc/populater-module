<?php

// This file is for functions specific to quizzes.
function get_quiz_prefix() {
  return 'quiz-';
};

function add_quiz_to_step($quiz_name, $course_name, $lesson_name = '', $topic_name = '') {
    global $wpdb;
    $quiz_id = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $quiz_name . "'"  );
    $topic_id = empty($topic_name) ? 0 : $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $topic_name . "'"  );
    $lesson_id = empty($lesson_name) ? 0 : $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $lesson_name . "'"  );
    $course_id = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $course_name . "'"  );

    update_post_meta( $quiz_id, 'course_id', $course_id  );
    $quiz_pro_id = get_post_meta($quiz_id)["quiz_pro_id"];

    $course_steps = get_post_meta($course_id, 'ld_course_steps', true);

    if ( $topic_id === 0 && $lesson_id === 0 ) {
      update_post_meta( $quiz_id, '_sfwd-quiz', [0, "sfwd-quiz_course" => $course_id, "sfwd-quiz_quiz_pro" => $quiz_pro_id] );
      $course_steps['steps']['h']['sfwd-quiz'] += [
        $quiz_id => []
      ];
    } else {
      if ( $topic_id === 0 ) {
        update_post_meta( $quiz_id, 'lesson_id', $lesson_id  );
        update_post_meta( $quiz_id, '_sfwd-quiz', [0, "sfwd-quiz_course" => $course_id, "sfwd-quiz_lesson" => $lesson_id, "sfwd-quiz_quiz_pro" => $quiz_pro_id, "sfwd-quiz_lesson_schedule" => 0] );
        $course_steps['steps']['h']['sfwd-lessons'][$lesson_id]['sfwd-quiz'] += [
          $quiz_id => []
        ];
      } else {
        update_post_meta( $quiz_id, 'lesson_id', $topic_id  );
        update_post_meta( $quiz_id, '_sfwd-quiz', [0, "sfwd-quiz_course" => $course_id, "sfwd-quiz_lesson" => $topic_id, "sfwd-quiz_quiz_pro" => $quiz_pro_id, "sfwd-quiz_lesson_schedule" => 0] );
        $course_steps['steps']['h']['sfwd-lessons'][$lesson_id]['sfwd-topic'][$topic_id]['sfwd-quiz'] += [
          $quiz_id => []
        ];
      }
    }
};

function create_quiz( $quiz_name ): bool {
    
  $faker = Faker\Factory::create();
    
  $quiz_id = wp_insert_post( 
    [
      'post_date'         => $faker->date( 'Y-m-d' ) . ' ' . $faker->time(),
      'post_date_gmt'     => $faker->date( 'Y-m-d' ) . ' ' . $faker->time(),
      'post_content'      => $faker->randomHtml(),
      'post_title'        => $quiz_name,
      'post_excerpt'      => $faker->sentence(),
      'post_status'       => 'publish',
      'post_type'         => 'sfwd-quiz',
      'post_name'         => $quiz_name, 
    ]
  );

  $quiz_pro_mapper = new \WpProQuiz_Model_QuizMapper();

  $quiz_pro = new \WpProQuiz_Model_Quiz();
  $quiz_pro->setPostId( $quiz_id );
  $quiz_pro->setText( 'text' );
  $quiz_pro = $quiz_pro_mapper->save( $quiz_pro );

  learndash_update_setting( $quiz_id, 'quiz_pro', $quiz_pro->getId() );
  update_post_meta($quiz_id, '_sfwd-quiz', [0, "sfwd-quiz_quiz_pro" => $quiz_pro->getId()] );
  return true;
};

function generate_quiz( $quiz_name, $course_iteration_flag, $lesson_iteration_flag, $topic_iteration_flag, $name_step_add_quiz ) {
    $fields = tangible_fields();
    
    $course_name = get_course_prefix() . $course_iteration_flag;
    $lesson_name = get_lesson_prefix() . $lesson_iteration_flag;
    $topic_name = get_topic_prefix() . $topic_iteration_flag;

    create_quiz( $quiz_name );

    if ( $name_step_add_quiz === 'topic' ) {
      add_quiz_to_step($quiz_name, $course_name, $lesson_name, $topic_name);
    } else if ( $name_step_add_quiz === 'lesson' ) {
      add_quiz_to_step($quiz_name, $course_name, $lesson_name);
    } else if ( $name_step_add_quiz === 'course' ) {
      add_quiz_to_step($quiz_name, $course_name);
    }

    return true;
};

function remove_quiz( $number ) {
  global $wpdb;
  $quiz_name = get_quiz_prefix() . $number;
  $postid = $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_title = '" . $quiz_name . "'");
  if($postid) {
    delete_metadata( 
      'post',
      $postid, 
      '', 
      true 
    );
    wp_delete_post($postid);
  }
};
