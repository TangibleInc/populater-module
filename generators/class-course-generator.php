<?php

declare(strict_types=1);
namespace Populater;

defined( 'ABSPATH' ) || exit;

use Tangible\Populater\AbstractGenerator;

/**
 * Class Course Generator
 */
class Course_Generator implements AbstractGenerator {
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
   * @return bool|\WP_Error 
   * 
   */
  public function generate( int $num_of_courses_to_add ): bool|\WP_Error {

    if( $num_of_courses_to_add == 0 ) {
      return true;
    }
    if( $num_of_courses_to_add < 0 ) {
      return new \WP_Error('course generation error', __( 'Input cannot be negative', 'tangible_populater' ) );
    }

    $fields = tangible_fields();
    $previous_num_of_courses = $fields->fetch_value( 'num_of_courses' );
    $total_num_of_courses = $previous_num_of_courses + $num_of_courses_to_add;

    for( $added_courses = 0; $added_courses < $num_of_courses_to_add; $added_courses++ ) {
      $course_name = $this->prefix . ( $previous_num_of_courses + $added_courses + 1); 
      if( $this->course_exists( $previous_num_of_courses + $added_courses + 1 ) ) {
        for( $num_of_courses_to_remove = 0; $num_of_courses_to_remove < $added_courses; $num_of_courses_to_remove++ ) {
          $this->remove_course( $previous_num_of_courses + $num_of_courses_to_remove + 1 );
        }
        return new \WP_Error( 'course generation error', __( 'Course already exists, please change the prefix and try again.', 'tangible_populater' ) );
      } else {
        $this->create_course( $course_name );
      }
    }

    $fields->store_value( 'num_of_courses', $total_num_of_courses );
    return true;
  }

  // create a single course
  public function create_course( string $course_name ): void {

    $faker = Faker\Factory::create();

    wp_insert_post(
      [
        'post_date'         => $faker->date( 'Y_m_d' ) . $faker->time(),
        'post_date_gmt'     => $faker->date( 'Y_m_d' ) . $faker->time(),
        'post_content'      => $faker->randomHtml(),
        'post_title'        => $course_name,
        'post_excerpt'      => $faker->sentence(),
        'post_status'       => 'publish',
        'post_type'         => 'sfwd-courses',
        'post_name'         => $course_name, 
      ]
    );
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


  function course_exists( int $number ): bool {
    global $wpdb;
    $course_name = $this->prefix . $number;
    $postid = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $course_name . "'" );
    if( $postid ) {
      return true;
    }
    return false;
  }
  
  function remove_course( int $number ): void {
    global $wpdb;
    $course_name = $this->prefix . $number;
    $postid = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $course_name . "'" );
    if( $postid ) {
      wp_delete_post( $postid );
    }
  } 

  function get_prefix(): string {
    return $this->prefix;
  }
} 

Course_Generator::$plugin = $plugin; 
