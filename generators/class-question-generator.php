<?php

declare(strict_types=1);
namespace Populater;

defined( 'ABSPATH' ) || exit;


use Tangible\Populater\AbstractGenerator;
use Faker\Factory as faker;
use WpProQuiz_Model_AnswerTypes;
use WpProQuiz_Model_Question;
use WpProQuiz_Model_QuestionMapper;

/**
 * Class Question Generator
 */
class Question_Generator implements AbstractGenerator {
  private static $instance = null;
  private $prefix = 'question-';
  static $plugin;

  public function __construct() {
    $prefix = 'question-';
  }

  public static function getInstance() {
    if(!self::$instance)
    {
      self::$instance = new Question_Generator();
    }
    return self::$instance;
  }

  /**
   * Generate questions.
   *
   * @param object $plugin
   * @return bool|\WP_Error 
   * 
   */
  public function generate( int $num_of_questions_to_add ): bool|\WP_Error {

    $fields = tangible_fields();
    $num_of_quizzes = $fields->fetch_value( 'num_of_quizzes' );
    $previous_num_of_questions = $fields->fetch_value( 'num_of_questions' );
    $total_num_of_questions = $previous_num_of_questions + $num_of_questions_to_add;
    $quiz_iteration_flag = 1;

    if( $num_of_questions_to_add == 0 ) {
      return true;
    }
    if( $num_of_questions_to_add < 0 ) {
      return new \WP_Error('question generation error', __( 'Input cannot be negative', 'tangible_populater' ) );
    }

    for( $added_questions = 0; $added_questions < $num_of_questions_to_add; $added_questions++ ) {
      $question_name = $this->prefix . ( $previous_num_of_questions + $added_questions + 1); 
      if( $this->question_exists( $question_name ) ) {
        for( $num_of_questions_to_remove = 0; $num_of_questions_to_remove < $added_questions; $num_of_questions_to_remove++ ) {
          $this->remove_question( $this->prefix . ( $previous_num_of_questions + $num_of_questions_to_remove + 1 ) );
        }
        return new \WP_Error( 'question generation error', __( 'Question already exists, please change the prefix and try again.', 'tangible_populater' ) );
      } else {
        if( $num_of_quizzes > 0 ) {
          $quiz_gen = Quiz_Generator::getInstance();
          $quiz_name = $quiz_gen->get_prefix() . $quiz_iteration_flag;
          $this->create_question( $question_name );
          $this->add_question_to_quiz( $quiz_name, $question_name );
          $quiz_iteration_flag++;
          if( $quiz_iteration_flag > $num_of_quizzes ) {
            $quiz_iteration_flag = 1;
          }
        } else {
          $this->create_question( $question_name );
        }
      }
    }

    $fields->store_value( 'num_of_questions', $total_num_of_questions );
    return true;
  }

  /**
  * function which creates a question
  * 
  * @param string $question_name
  * @return void
  */
  public function create_question( string $question_name ): bool|\WP_Error {
    
    $faker = faker::create();
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
    
    $question_pro = new WpProQuiz_Model_Question; 
    $question_pro_mapper = new WpProQuiz_Model_QuestionMapper;

    $post_id = wp_insert_post( $post );
    $question_pro->setQuestion( $faker->sentence( 6 ) );
    $question_pro->setTitle( $question_name );
    $question_pro->setAnswerData( $this->createAnswers() );
    $question_pro = $question_pro_mapper->save( $question_pro );

    $question_pro_id = $question_pro->getId();

    add_post_meta( $post_id, 'question_pro_id', $question_pro_id );

    return true; 
  }

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
  }
  
  /**
   * function which removes all generated courses from the Database
   *
   * @return void
   */
  function remove_generated(): void {
    $fields = tangible_fields();  
   
    if( null == $fields->fetch_value( 'num_of_questions' ) ) {
      $fields->store_value( 'num_of_questions', 0 );
      return;
    } else {
      $num_of_questions = $fields->fetch_value( 'num_of_questions' );
    }

    for( $i=0; $i < $num_of_questions; $i++ ) {
      $this->remove_question( $this->prefix . ( $i + 1 ) );
    }

    $fields->store_value( 'num_of_questions', 0 );
  }


  function question_exists( string $question_name ): bool {
    global $wpdb;

    $postid = $wpdb->get_var( 
      "SELECT id 
       FROM $wpdb->posts 
       WHERE post_title = '" . $question_name . "'" 
    );

    if( $postid ) {
      return true;
    }
    return false;
  }
  
  function remove_question( string $question_name ): void {
    // TODO: Add meta that these are fake during creation.
    //       Confirm the fake meta is there before deletion.
    global $wpdb;

    $postid = $wpdb->get_var( 
      "SELECT id 
       FROM $wpdb->posts 
       WHERE post_title = '" . $question_name . "'" 
    );

    if( $postid ) {
      delete_metadata( 
        meta_type:'post', 
        object_id: $postid, 
        meta_key: '', 
        delete_all:true 
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
  } 

  function get_all_questions( string $column_name = '*' ): array {
    global $wpdb;
    $question_info = $wpdb->get_results("SELECT $column_name FROM $wpdb->posts WHERE post_type='sfwd-question'", ARRAY_A);
    return $question_info;
  }

  function get_question_id( string $question_name ): int {
    global $wpdb;
    $question_id = $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_title='$question_name' AND post_type='sfwd-question'");
    return (int) $question_id ?? 0;
  }

  function get_id_of_last_quiz_created(): int {
    global $wpdb;

    $question_id = $wpdb->get_var( 
      "SELECT DISTINCT
        meta_value
       FROM $wpdb->postmeta
       WHERE meta_key = \"question_pro_id\"
       ORDER BY meta_value DESC
       LIMIT 1
      "
    );

    if( $question_id ) {
      return (int) $question_id;
    }
    else {
      return 1;
    }
  }

  function get_prefix(): string {
    return $this->prefix;
  }

  function createAnswers(): array {
    $faker = faker::create();
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
  }
} 

// Question_Generator::$plugin = $plugin; 
