<?php

declare(strict_types=1);
namespace Populater;

defined( 'ABSPATH' ) || exit;

use Tangible\Populater\AbstractGenerator;
use Faker\Factory as faker;

/**
 * Class Quiz Generator
 */
class Quiz_Generator implements AbstractGenerator {
  private static $instance = null;
  private $prefix = 'quiz-';
  private static $step_name = 'course';
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

  function set_step_name( string $name ) {
    self::$step_name = $name;
  }

  /** 
   * function which generates quizzes
   * 
   * @param int $num_of_quizzes_to_add
   * @return bool|\WP_Error 
   */
  function generate( int $num_of_quizzes_to_add ): bool|\WP_Error {

    $name_step_add_quiz = self::$step_name;
    $fields = tangible_fields();
    $num_of_courses = $fields->fetch_value( 'num_of_courses' );
    $num_of_lessons = $fields->fetch_value( 'num_of_lessons' );
    $num_of_topics = $fields->fetch_value( 'num_of_topics' );
    $previous_num_of_quizzes = $fields->fetch_value( 'num_of_quizzes' );
    $total_num_of_quizzes = $previous_num_of_quizzes + $num_of_quizzes_to_add;
    $course_iteration_flag = 1;
    $lesson_iteration_flag = 1;
    $topic_iteration_flag = 1;

    if( $num_of_quizzes_to_add == 0 ) {
      return true;
    }
    if( $num_of_quizzes_to_add < 0 ) {
      return new \WP_Error( 'quiz generation error', __( 'Input cannot be negative', 'tangible_populater' ) );
    }

    for( $added_quizzes = 0; $added_quizzes < $num_of_quizzes_to_add; $added_quizzes++ ) { 
      $quiz_name = $this->prefix . ( $previous_num_of_quizzes + $added_quizzes + 1 ); 
      if( $this->quiz_exists( $quiz_name ) ) {
        for( $num_of_quizzes_to_remove = 0; $num_of_quizzes_to_remove < $added_quizzes; $num_of_quizzes_to_remove++ ) {
          $this->remove_quiz( $this->prefix . ( $previous_num_of_quizzes + $num_of_quizzes_to_remove + 1 ) );
        }
        return new \WP_Error( 'lesson generation error', __( 'Lesson already exists, please change the prefix and try again.', 'tangible_populater' ) );
      } else {
        $quiz_success = $this->create_quiz( $quiz_name, ($previous_num_of_quizzes + $added_quizzes + 1) );
        if(is_wp_error( $quiz_success ) ) {
          return $quiz_success;
        }
        $course_gen = Course_Generator::getInstance();
        $course_name = $course_gen->get_prefix() . $course_iteration_flag;
        $lesson_gen = Lesson_Generator::getInstance();
        $lesson_name = $lesson_gen->get_prefix() . $lesson_iteration_flag;
        $topic_gen = Topic_Generator::getInstance();
        $topic_name = $topic_gen->get_prefix() . $topic_iteration_flag;
        if ( $name_step_add_quiz === 'topic' ) {
          $this->add_quiz_to_step($quiz_name, $course_name, $lesson_name, $topic_name);
        } else if ( $name_step_add_quiz === 'lesson' ) {
          $this->add_quiz_to_step($quiz_name, $course_name, $lesson_name);
        } else if ( $name_step_add_quiz === 'course' ) {
          $this->add_quiz_to_step($quiz_name, $course_name);
        }
        $course_iteration_flag++;
        $lesson_iteration_flag++;
        $topic_iteration_flag++;
        if($course_iteration_flag > $num_of_courses) {
          $course_iteration_flag = 1;
        }
        if($lesson_iteration_flag > $num_of_lessons) {
            $lesson_iteration_flag = 1;
        }
        if($topic_iteration_flag > $num_of_topics) {
          $topic_iteration_flag = 1;
      }
      }
    }

    $fields->store_value( 'num_of_quizzes', $total_num_of_quizzes );
    return true;
  }

  function add_quiz_to_step($quiz_name, $course_name, $lesson_name = '', $topic_name = '') {
    global $wpdb;
    $quizid = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $quiz_name . "'"  );
    $topicid = empty($topic_name) ? 0 : $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $topic_name . "'"  );
    $lessonid = empty($lesson_name) ? 0 : $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $lesson_name . "'"  );
    $courseid = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $course_name . "'"  );

    update_post_meta( $quizid, 'course_id', $courseid  );
    $quiz_pro_id = get_post_meta($quizid)["quiz_pro_id"];

    if ( $topicid === 0 && $lessonid === 0 ) {
      update_post_meta( $quizid, '_sfwd-quiz', [0, "sfwd-quiz_course" => $courseid, "sfwd-quiz_quiz_pro" => $quiz_pro_id] );
    } else {
      if ( $topicid === 0 ) {
        var_dump('lessonid : ', $lessonid);
        update_post_meta( $quizid, 'lesson_id', $lessonid  );
        update_post_meta( $quizid, '_sfwd-quiz', [0, "sfwd-quiz_course" => $courseid, "sfwd-quiz_lesson" => $lessonid, "sfwd-quiz_quiz_pro" => $quiz_pro_id, "sfwd-quiz_lesson_schedule" => 0] );
      } else {
        var_dump('topicid : ', $topicid);
        update_post_meta( $quizid, 'lesson_id', $topicid  );
        update_post_meta( $quizid, '_sfwd-quiz', [0, "sfwd-quiz_course" => $courseid, "sfwd-quiz_lesson" => $topicid, "sfwd-quiz_quiz_pro" => $quiz_pro_id, "sfwd-quiz_lesson_schedule" => 0] );
      }
    }

  }

  /**
  * function which creates a quiz by inserting it into the post table and calling learndash update settings to save it's meta
  * 
  * @param string $quiz_name
  * @return bool|WP_Error 
  */
  function create_quiz( $quiz_name ): bool|\WP_Error {
    
    $faker = faker::create();
      
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

  function get_all_quizes( string $column_name='*' ): array{
    global $wpdb;
    $quiz_ids = $wpdb->get_results("SELECT $column_name FROM $wpdb->posts WHERE post_type = 'sfwd-quiz'", ARRAY_A);
    return $quiz_ids;
  }

  function get_quiz_id( string $quiz_name ): int {
    global $wpdb;
    $quiz_id = $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_title='$quiz_name' AND post_type='sfwd-quiz'");
    return (int) $quiz_id ?? 0;
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
