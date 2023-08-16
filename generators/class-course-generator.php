<?php

declare(strict_types=1);
namespace Populater;

defined( 'ABSPATH' ) || exit;

use Faker\Factory as faker;

/**
 * Class Course Generator
 */
class Course_Generator {
  private static $instance = null;
  private $prefix = 'course-';
  static $plugin;

  public function __construct() {
    $prefix = 'course-';
  }

  public static function getInstance() {
    if(!self::$instance)
    {
      self::$instance = new Course_Generator();
    }
    return self::$instance;
  }

  /**
   * Generate courses.
   *
   * @param object $plugin
   * @return bool
   * 
   */
  public function generate( int $num_of_courses_to_add, string $course_name ): bool {

    $faker = faker::create();

    $fields = tangible_fields();
    $previous_num_added = $fields->fetch_value( 'num_of_courses' );
    $total_num_of_courses = $previous_num_added + $num_of_courses_to_add;

    wp_insert_post(
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

    $fields->store_value( 'num_of_courses', $total_num_of_courses );
    return true;
  }

  /**
   * function which removes all generated courses from the Database
   *
   * @return void
   */
  function remove_generated(): void{
    $fields = tangible_fields();  
   
    if( null == $fields->fetch_value( 'num_of_courses' ) ) {
      $fields->store_value( 'num_of_courses', 0 );
      return;
    } else {
      $num_of_courses = $fields->fetch_value( 'num_of_courses' );
    }

    for( $i=0; $i < $num_of_courses; $i++ ) {
      $this->remove_course( $i + 1 );
    }

    $fields->store_value( 'num_of_courses', 0 );
  }
  
  function remove_course( int $number ): void {
    global $wpdb;
    $course_name = $this->prefix . $number;
    $postid = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $course_name . "'" );
    if( $postid ) {
      wp_delete_post( $postid );
    }
  } 

  function get_all_courses( string $column_name = '*'): array {
    global $wpdb;
    $course_info = $wpdb->get_results("SELECT $column_name FROM $wpdb->posts WHERE post_type = 'sfwd-courses'", ARRAY_A);
    return $course_info;
  }

  function get_course_id( string $course_name ): int {
    global $wpdb;
    $course_id = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '$course_name' AND post_type='sfwd-courses'");
    return (int) $course_id ?? 0;
  }

  function get_prefix(): string {
    return $this->prefix;
  }
} 

Course_Generator::$plugin = $plugin; 