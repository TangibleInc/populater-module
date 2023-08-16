<?php

declare(strict_types=1);
namespace Populater;

defined( 'ABSPATH' ) || exit;


use Tangible\Populater\AbstractGenerator;

use Faker\Factory as faker;

/**
 * Class Lesson Generator
 */
class Lesson_Generator implements AbstractGenerator {
  private static $instance = null;
  private $prefix = 'lesson-';
  static $fields;

  private function __construct() {
    $prefix = 'lesson-';
  }

  public static function getInstance() {
    if(!self::$instance)
    {
      self::$instance = new Lesson_Generator();
    }
    return self::$instance;
  }

  /** 
   * function which generates lessons
   * 
   * @param object $plugin
   * @return bool|\WP_Error 
   */
  function generate( int $num_of_lessons_to_add ): bool|\WP_Error {

    $fields = tangible_fields();
    $num_of_courses = $fields->fetch_value( 'num_of_courses' );
    $previous_num_of_lessons = $fields->fetch_value( 'num_of_lessons' );
    $total_num_of_lessons = $previous_num_of_lessons + $num_of_lessons_to_add;
    $course_iteration_flag = 1;

    if( $num_of_courses == 0 ) {
      return new \WP_Error( 'lesson generation error', __( 'Must create at least 1 course before generating lessons', 'tangible_populater' ) );
    }
    if( $num_of_lessons_to_add == 0 ) {
      return true;
    }
    if( $num_of_lessons_to_add < 0 ) {
      return new \WP_Error( 'lesson generation error', __( 'Input cannot be negative', 'tangible_populater' ) );
    }
    
    for( $added_lessons = 0; $added_lessons < $num_of_lessons_to_add; $added_lessons++ ) { 
      $lesson_name = $this->prefix . ( $previous_num_of_lessons + $added_lessons + 1 ); 
      if( $this->lesson_exists( $lesson_name ) ) {
        for( $num_of_lessons_removed = 0; $num_of_lessons_removed < $added_lessons; $num_of_lessons_removed++ ) {
          $this->remove_lesson( $this->prefix . ( $previous_num_of_lessons + $num_of_lessons_removed + 1 ) );
        }
        return new \WP_Error( 'lesson generation error', __( 'Lesson already exists, please change the prefix and try again.', 'tangible_populater' ) );
      } else {
        $this->create_lesson( $lesson_name );
        $course_gen = Course_Generator::getInstance();
        $course_name = $course_gen->get_prefix() . $course_iteration_flag;
        $this->add_lesson_to_course( $lesson_name, $course_name );
        $course_iteration_flag++;
        if($course_iteration_flag > $num_of_courses) {
          $course_iteration_flag = 1;
        }
      }
    }

    $fields->store_value( 'num_of_lessons', $total_num_of_lessons );
    return true;
  }

  // create single lesson
  function create_lesson( string $lesson_name ): void {

    $faker = faker::create();

    wp_insert_post (
      [
        'post_date'         => $faker->date( 'Y_m_d' ) . $faker->time(),
        'post_date_gmt'     => $faker->date( 'Y_m_d' ) . $faker->time(),
        'post_content'      => $faker->randomHtml(),
        'post_title'        => $lesson_name,
        'post_excerpt'      => $faker->sentence(),
        'post_status'       => 'publish',
        'post_type'         => 'sfwd-lessons',
        'post_name'         => $lesson_name, 
      ]
    );
  }

  function add_lesson_to_course( string $lesson_name, string $course_name ): void {
    global $wpdb;
    $courseid = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $course_name . "'"  );
    $lessonid = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $lesson_name . "'"  );
    update_post_meta( $lessonid, 'course_id', $courseid  );
    update_post_meta( $lessonid, '_swfd-lessons', [0, "swfd-lessons_course" => $courseid] );
  }

  /**
   * function which removes all the generated users from the DB
   * 
   * @return void
   */
  function remove_generated(): void {
    $fields = tangible_fields();

    if( null == $fields->fetch_value( 'num_of_lessons' ) ) {
      $fields->store_value( 'num_of_lessons', 0 );
      return;
    } else {
      $num_of_lessons = $fields->fetch_value( 'num_of_lessons' );
    }

    for( $i=0; $i < $num_of_lessons; $i++ ) {
      $this->remove_lesson( $this->prefix .  ( $i + 1 ) );
    }

    $fields->store_value( 'num_of_lessons', 0);
  }

  // we can point a lesson to a course by using postmeta
  
  function lesson_exists( string $lesson_name ): bool {
    global $wpdb;
    $lessonid = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $lesson_name . "'");
    if( $lessonid ) {
      return true;
    }
    return false;
  }

  function remove_lesson( string $lesson_name ): void {
    global $wpdb;
    $postid = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $lesson_name . "'" );
    if( $postid ) {
      delete_metadata( 
        meta_type:'post', 
        object_id: $postid, 
        meta_key: '', 
        delete_all:true 
      );
      wp_delete_post( $postid );
    }
  }

  function get_all_lessons( string $column_name = '*' ): array {
    global $wpdb;
    $lesson_info = $wpdb->get_results("SELECT $column_name FROM $wpdb->posts WHERE post_type = 'sfwd-lessons'", ARRAY_A);
    return $lesson_info;
  }

  function get_lesson_id( string $lesson_name ): int {
    global $wpdb;
    $lesson_id = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '$lesson_name' AND post_type='sfwd-lessons'");
    return (int) $lesson_id ?? 0;
  }

  function get_prefix(): string {
    return $this->prefix;
  }
}

Lesson_Generator::$fields = tangible_fields();
