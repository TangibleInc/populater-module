<?php

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

use Tangible\Populater\AbstractGenerator;

/**
 * Class Quiz Generator
 */
class Quiz_Generator implements AbstractGenerator {
  private static $instance = null;
  private $prefix = 'quiz-';
  static $fields;

  private function __construct() {
    $prefix = 'quiz-';
  }

  public static function getInstance() {
    if(!self::$instance)
    {
      self::$instance = new Quiz_Generator();
    }
    return self::$instance;
  }

  /** 
   * function which generates quizzes
   * 
   * @param int $num_of_quizzes_to_add
   * @return bool|WP_Error 
   */
  function generate( int $num_of_quizzes_to_add ): bool|WP_Error {

    $fields = tangible_fields();
    $previous_num_of_quizzes = $fields->fetch_value( 'num_of_quizzes' );
    $total_num_of_quizzes = $previous_num_of_quizzes + $num_of_quizzes_to_add;

    if( $num_of_quizzes_to_add == 0 ) {
      return true;
    }
    if( $num_of_quizzes_to_add < 0 ) {
      return new WP_Error( 'quiz generation error', __( 'Input cannot be negative', 'tangible_populater' ) );
    }
    
    for( $added_quizzes = 0; $added_quizzes < $num_of_quizzes_to_add; $added_quizzes++ ) { 
      $quiz_name = $this->prefix . ( $previous_num_of_quizzes + $added_quizzes + 1 ); 
      if( $this->quiz_exists( $quiz_name ) ) {
        for( $num_of_quizzes_to_remove = 0; $num_of_quizzes_to_remove < $added_quizzes; $num_of_quizzes_to_remove++ ) {
          $this->remove_quiz( $this->prefix . ( $previous_num_of_quizzes + $num_of_quizzes_to_remove + 1 ) );
        }
        return new WP_Error( 'lesson generation error', __( 'Lesson already exists, please change the prefix and try again.', 'tangible_populater' ) );
      } else {
        $quiz_success = $this->create_quiz( $quiz_name, ($previous_num_of_quizzes + $added_quizzes + 1) );
        if(is_wp_error( $quiz_success ) ) {
          return $quiz_success;
        }
      }
    }

    $fields->store_value( 'num_of_quizzes', $total_num_of_quizzes );
    return true;
  }

  /**
  * function which creates a quiz by inserting it into the post table and calling learndash update settings to save it's meta
  * 
  * @param string $quiz_name
  * @return bool|WP_Error 
  */
  function create_quiz( $quiz_name ): bool|WP_Error {
    
    $faker = Faker\Factory::create();
      
    $quiz_id = wp_insert_post( 
      [
        'post_date'         => $faker->date( 'Y_m_d' ) . $faker->time(),
        'post_date_gmt'     => $faker->date( 'Y_m_d' ) . $faker->time(),
        'post_content'      => $faker->randomHtml(),
        'post_title'        => $quiz_name,
        'post_excerpt'      => $faker->sentence(),
        'post_status'       => 'publish',
        'post_type'         => 'sfwd-quiz',
        'post_name'         => $quiz_name, 
      ]
    );

    $quiz_pro_mapper = new WpProQuiz_Model_QuizMapper();

    $quiz_pro = new WpProQuiz_Model_Quiz();
    $quiz_pro->setPostId( $quiz_id );
    $quiz_pro->setText( 'AAZZAAZZ' );
    $quiz_pro = $quiz_pro_mapper->save( $quiz_pro );

    learndash_update_setting( $quiz_id, 'quiz_pro', $quiz_pro->getId() );
    return true;
  }


  /**
   * function which removes all the generated users from the DB
   * 
   * @return void
   */
  function remove_generated(): void {
    $fields = tangible_fields();

    if( null == $fields->fetch_value( 'num_of_quizzes' ) ) {
      $fields->store_value( 'num_of_quizzes', 0 );
      return;
    } else {
      $num_of_quizzes = $fields->fetch_value( 'num_of_quizzes' );
    }

    for( $i=0; $i < $num_of_quizzes; $i++ ) {
      $this->remove_quiz( $this->prefix . ( $i + 1 ) );
    }

    $fields->store_value( 'num_of_quizzes', 0 );
  }

  // we can point a lesson to a course by using postmeta
  
  function quiz_exists( string $quiz_name ): bool {
    global $wpdb;
    $quizid = $wpdb->get_var( 
      "SELECT id 
       FROM $wpdb->posts 
       WHERE post_title = '" . $quiz_name . "'"
    );

    if( $quizid ) {
      return true;
    }
    return false;
  }

  function remove_quiz( string $quiz_name ): void {
    global $wpdb;
    $postid = $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_title = '" . $quiz_name . "'");
    if($postid) {
      delete_metadata( 
        meta_type:'post', 
        object_id: $postid, 
        meta_key: '', 
        delete_all:true 
      );
      wp_delete_post($postid);
    }
  }
  
    
  function get_id_of_last_quiz_created(): int {
    global $wpdb;

    $quiz_id = $wpdb->get_var( 
      "SELECT DISTINCT
        meta_value
       FROM $wpdb->postmeta 
       WHERE meta_key = \"quiz_pro_id\"
       ORDER BY meta_value DESC
       LIMIT 1
      "
    );
    if( $quiz_id ) {
      return (int) $quiz_id;
    }
    else {
      return 1;
    }
  }

  function get_prefix(): string {
    return $this->prefix;
  }
}

Quiz_Generator::$fields = tangible_fields();
