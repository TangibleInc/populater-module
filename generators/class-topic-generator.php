<?php

declare(strict_types=1);
namespace Populater;

defined( 'ABSPATH' ) || exit;


use Tangible\Populater\AbstractGenerator;
use Faker\Factory as faker;

/**
 * Class Topic Generator
 */
class Topic_Generator implements AbstractGenerator {
  private static $instance = null;
  private $prefix = 'topic-';
  static $fields;

  private function __construct() {
    $prefix = 'topic-';
  }

  public static function getInstance() {
    if(!self::$instance)
    {
      self::$instance = new Topic_Generator();
    }
    return self::$instance;
  }

  /** 
   * function which generates topics
   * 
   * @param object $plugin
   * @return bool|\WP_Error 
   */
  function generate( int $num_of_topics_to_add ): bool|\WP_Error {

    $fields = tangible_fields();
    $num_of_courses = $fields->fetch_value( 'num_of_courses' );
    $num_of_lessons = $fields->fetch_value( 'num_of_lessons' );
    $previous_num_of_topics = $fields->fetch_value( 'num_of_topics' );
    $total_num_of_topics = $previous_num_of_topics + $num_of_topics_to_add;
    $course_iteration_flag = 1;
    $lesson_iteration_flag = 1;

    if( $num_of_lessons == 0 || $num_of_courses == 0 ) {
      return new \WP_Error( 'topic generation error', __( 'Must create at least 1 course / lesson before generating topics', 'tangible_populater' ) );
    }
    if( $num_of_topics_to_add == 0 ) {
      return true;
    }
    if( $num_of_topics_to_add < 0 ) {
      return new \WP_Error( 'topics generation error', __( 'Input cannot be negative', 'tangible_populater' ) );
    }
    
    for( $added_topics = 0; $added_topics < $num_of_topics_to_add; $added_topics++ ) { 
      $topic_name = $this->prefix . ( $previous_num_of_topics + $added_topics + 1 );
      if( $this->topic_exists( $topic_name ) ) {
        for( $num_of_topics_removed = 0; $num_of_topics_removed < $added_topics; $num_of_topics_removed++ ) {
          $this->remove_topic( $this->prefix . ( $previous_num_of_topics + $num_of_topics_removed + 1 ) );
        }
        return new \WP_Error( 'topic generation error', __( 'Topic already exists, please change the prefix and try again.', 'tangible_populater' ) );
      } else {
        $this->create_topic( $topic_name );
        $course_gen = Course_Generator::getInstance();
        $course_name = $course_gen->get_prefix() . $course_iteration_flag;
        $lesson_gen = Lesson_Generator::getInstance();
        $lesson_name = $lesson_gen->get_prefix() . $lesson_iteration_flag;
        $this->add_topic_to_lesson( $topic_name, $lesson_name, $course_name );
        $course_iteration_flag++;
        $lesson_iteration_flag++;
        if($course_iteration_flag > $num_of_courses) {
          $course_iteration_flag = 1;
        }
        if($lesson_iteration_flag > $num_of_lessons) {
            $lesson_iteration_flag = 1;
        }
      }
    }

    $fields->store_value( 'num_of_topics', $total_num_of_topics );
    return true;
  }

  // create single topic
  function create_topic( string $topic_name ): void {

    $faker = faker::create();

    wp_insert_post (
      [
        'post_date'         => $faker->date( 'Y-m-d' ) . ' ' . $faker->time(),
        'post_date_gmt'     => $faker->date( 'Y-m-d' ) . ' ' . $faker->time(),
        'post_content'      => $faker->randomHtml(),
        'post_title'        => $topic_name,
        'post_excerpt'      => $faker->sentence(),
        'post_status'       => 'publish',
        'post_type'         => 'sfwd-topic',
        'post_name'         => $topic_name, 
      ]
    );
  }

  function add_topic_to_lesson( string $topic_name, string $lesson_name, string $course_name ): void {
    global $wpdb;
    $topicid = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $topic_name . "'"  );
    $lessonid = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $lesson_name . "'"  );
    $courseid = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $course_name . "'"  );
    update_post_meta( $topicid, 'course_id', $courseid  );
    update_post_meta( $topicid, 'lesson_id', $lessonid  );
    update_post_meta( $topicid, '_sfwd-topic', [0, "swfd-topic_course" => $courseid, "sfwd-topic_lesson" => $lessonid ] );
  }

  /**
   * function which removes all the generated topics from the DB
   * 
   * @return void
   */
  function remove_generated(): void {
    $fields = tangible_fields();

    if( null == $fields->fetch_value( 'num_of_topics' ) ) {
      $fields->store_value( 'num_of_topics', 0 );
      return;
    } else {
      $num_of_topics = $fields->fetch_value( 'num_of_topics' );
    }

    for( $i=0; $i < $num_of_topics; $i++ ) {
      $this->remove_topic( $this->prefix .  ( $i + 1 ) );
    }

    $fields->store_value( 'num_of_topics', 0);
  }

  // we can point a topic to a lesson by using postmeta
  
  function topic_exists( string $topic_name ): bool {
    global $wpdb;
    $topicid = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $topic_name . "'");
    if( $topicid ) {
      return true;
    }
    return false;
  }

  function remove_topic( string $topic_name ): void {
    global $wpdb;
    $postid = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $topic_name . "'" );
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

  function get_prefix(): string {
    return $this->prefix;
  }
}

Topic_Generator::$fields = tangible_fields();
