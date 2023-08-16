<?php

declare(strict_types=1);
namespace Populater;

defined( 'ABSPATH' ) || exit;

use Faker\Factory as faker;

/**
 * Class Lesson Generator
 */
class Lesson_Generator {
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
   * @return bool
   */
  function generate( int $num_of_lessons_to_add, string $lesson_name, int $course_iteration_flag ): bool {

    $fields = tangible_fields();
    $faker = faker::create();

    $previous_num_of_lessons = $fields->fetch_value( 'num_of_lessons' );
    $total_num_of_lessons = $previous_num_of_lessons + $num_of_lessons_to_add;

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

    $course_gen = Course_Generator::getInstance();
    $course_name = $course_gen->get_prefix() . $course_iteration_flag;
    $this->add_lesson_to_course( $lesson_name, $course_name );

    $fields->store_value( 'num_of_lessons', $total_num_of_lessons );
    return true;
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
