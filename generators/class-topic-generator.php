<?php

declare(strict_types=1);
namespace Populater;

defined( 'ABSPATH' ) || exit;

use Faker\Factory as faker;

/**
 * Class Topic Generator
 */
class Topic_Generator {
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
   * @return bool 
   */
  function generate( int $num_of_topics_to_add, string $topic_name, int $course_iteration_flag, int $lesson_iteration_flag ): bool {

    $fields = tangible_fields();
    $faker = faker::create();
    
    $previous_num_of_topics = $fields->fetch_value( 'num_of_topics' );
    $total_num_of_topics = $previous_num_of_topics + $num_of_topics_to_add;
    
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

    $course_gen = Course_Generator::getInstance();
    $course_name = $course_gen->get_prefix() . $course_iteration_flag;
    $lesson_gen = Lesson_Generator::getInstance();
    $lesson_name = $lesson_gen->get_prefix() . $lesson_iteration_flag;

    $this->add_topic_to_lesson( $topic_name, $lesson_name, $course_name );
 
    $fields->store_value( 'num_of_topics', $total_num_of_topics );
    return true;
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

  function get_all_topics( string $column_name = '*' ): array{
    global $wpdb;
    $topic_info= $wpdb->get_results( "SELECT $column_name FROM $wpdb->posts WHERE post_type = 'sfwd-topic'", ARRAY_A );
    return $topic_info;
  }

  function get_topic_id( string $topic_name ){
    global $wpdb;
    $topic_id = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '$topic_name' AND post_type='sfwd-topic'" );
    return (int) $topic_id ?? 0;
  }

  function get_prefix(): string {
    return $this->prefix;
  }
}

Topic_Generator::$fields = tangible_fields();
