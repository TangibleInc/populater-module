<?php

// This file is for functions specific to questions.
function get_question_prefix() {
  return 'question-';
};

function createAnswers() {
    $faker = Faker\Factory::create();
    $answers = [];

    $answer = new WpProQuiz_Model_AnswerTypes;
    $answer->setAnswer( 'correct answer' );
    $answer->setCorrect( 1 );
    
    $answers[] = $answer; 

    for( $i = 0; $i < 3; $i++ ) {
      $answer = new WpProQuiz_Model_AnswerTypes;
      $answer->setAnswer( 'incorrect answer ' . ( $i + 1 ) );
      $answer->setCorrect( 0 );
      $answers[] = $answer;
    }
    // tangible()->log( $answers );
    return $answers;
};

function create_question( string $question_name ) {
    
    $faker = Faker\Factory::create();
    $post = [
      'post_date'         => $faker->date( 'Y_m_d' ) . $faker->time(),
      'post_date_gmt'     => $faker->date( 'Y_m_d' ) . $faker->time(),
      'post_content'      => $faker->randomHtml(),
      'post_title'        => $question_name,
      'post_excerpt'      => $faker->sentence(),
      'post_status'       => 'publish',
      'post_type'         => 'sfwd-question',
      'post_name'         => $question_name, 
    ];
    
    $question_pro = new WpProQuiz_Model_Question(); 
    $question_pro_mapper = new WpProQuiz_Model_QuestionMapper();

    $post_id = wp_insert_post( $post );
    $question_pro->setQuestion( $faker->sentence( 6 ) );
    $question_pro->setTitle( $question_name );
    $question_pro->setAnswerData( createAnswers() );
    $question_pro = $question_pro_mapper->save( $question_pro );

    $question_pro_id = $question_pro->getId();

    add_post_meta( $post_id, 'question_pro_id', $question_pro_id );

    return get_post($post_id); 
};

function add_question_to_quiz( string $quiz_name, string $question_name ) {
    global $wpdb;

    $quiz_id = $wpdb->get_var( 
      "SELECT id 
       FROM $wpdb->posts 
       WHERE post_title = '" . $quiz_name . "'"  
    );

    $question_id = $wpdb->get_var( 
      "SELECT id 
       FROM $wpdb->posts 
       WHERE post_title = '" . $question_name . "'"  
    );

    update_post_meta( $question_id, 'quiz_id', $quiz_id );
    update_post_meta( $question_id, '_sfwd-question', [
      '0' => '',
      'sfwd-question_quiz' => $quiz_id
    ]); 
};

function generate_question( $question_name, $quiz_iteration_flag, $parent_post ) {

    $fields = tangible_fields();

    $quiz_name = get_quiz_prefix() . $quiz_iteration_flag;
    $question = create_question( $question_name );

    if ( $parent_post === false ) return $question;

    add_question_to_quiz( $quiz_name, $question_name );

    return $question;
};

function remove_question( $number ) {
  global $wpdb;
  $question_name = get_question_prefix() . $number;

  $postid = $wpdb->get_var( 
    "SELECT id 
     FROM $wpdb->posts 
     WHERE post_title = '" . $question_name . "'" 
  );

  if( $postid ) {
    delete_metadata( 
      'post',
      $postid, 
      '', 
      true 
    );

    wp_delete_post( $postid );
  }
  
  // this should be done by the pro id instead, because title is not very 
  // reliable, could end up deleting some legit things, or we'll be forced to 
  //specific titles.
  $table = LDLMS_DB::get_table_name( 'quiz_question' );
  global $wpdb;
  $wpdb->delete(
    $table, 
    ['title' => $question_name],
  );
};
