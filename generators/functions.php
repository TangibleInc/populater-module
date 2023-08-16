<?php 

$populater->check_step = function ( $post_type , $add_step_to = '' ) use ( $populater ) {

    if ( empty($post_type) ) return false;
    $fields = tangible_fields();

    $check = true;
    switch ( $post_type ) {
        case 'course':
            $check = true;
            break;

        case 'lesson':
            $num_of_courses = $fields->fetch_value( 'num_of_courses' );
            if ( $num_of_courses == 0 ) $check = false;
            break;

        case 'topic':
            $num_of_courses = $fields->fetch_value( 'num_of_courses' );
            $num_of_lessons = $fields->fetch_value( 'num_of_lessons' );
            if ( $num_of_courses == 0 || $num_of_lessons == 0 ) $check = false;
            break;

        case 'quiz': 
            if ( $add_step_to = 'course' ) {
                $num_of_courses = $fields->fetch_value( 'num_of_courses' );
                if ( $num_of_courses == 0 ) $check = false;
            } else if ( $add_step_to = 'lesson' ) {
                $num_of_lessons = $fields->fetch_value( 'num_of_lessons' );
                if ( $num_of_lessons == 0 ) $check = false;
            } else if ( $add_step_to = 'topic' ) {
                $num_of_topics = $fields->fetch_value( 'num_of_topics' );
                if ( $num_of_topics == 0 ) $check = false; 
            } else {
                $check = false;
            }
            break;

        case 'question':
            $num_of_quizzes = $fields->fetch_value( 'num_of_quizzes' );
            if ( $num_of_quizzes == 0 ) $check = false;
            break;
        
        default:
            $check = false;
            break;
    };

    return $check;
};

$populater->step_exists = function ( $name ) use ( $populater ) {

    global $wpdb;
    $postid = $wpdb->get_var( 
      "SELECT id 
       FROM $wpdb->posts 
       WHERE post_title = '" . $name . "'" 
    );

    return $postid ? true : false;
};

$populater->generate_step = function ( $post_type, $num_to_add, $name, $iteration_flag_array, $add_step_to = '' ) use ( $populater ) {

    switch ( $post_type ) {
        case 'course':
            $course_gen = \Populater\Course_Generator::getInstance();
            $course_gen->generate( $num_to_add, $name );
            break;

        case 'lesson': 
            $lesson_gen = \Populater\Lesson_Generator::getInstance();
            $lesson_gen->generate( $num_to_add, $name, $iteration_flag_array['course'] );
            break;

        case 'topic':
            $topic_gen = \Populater\Topic_Generator::getInstance();
            $topic_gen->generate( $num_to_add, $name, $iteration_flag_array['course'], $iteration_flag_array['lesson'] );
            break;

        case 'quiz':
            $quiz_gen = \Populater\Quiz_Generator::getInstance();
            $quiz_gen->generate( $num_to_add, $name, $iteration_flag_array['course'], $iteration_flag_array['lesson'], $iteration_flag_array['topic'], $add_step_to );
            break;

        case 'question':
            $question_gen = \Populater\Question_Generator::getInstance();
            $question_gen->generate( $num_to_add, $name, $iteration_flag_array['quiz'] );
            break;
        
        default:
            break;
    }

};

$populater->add_step = function ( $num_to_add, $post_type, $fetch_value_name, $prefix, $add_step_to = '' ) use ( $populater ) { 

    if ( $num_to_add == 0 ) return true;
    if ( $num_to_add < 0 ) return false;

    $check_step = $populater->check_step($post_type, $add_step_to);
    if ( !$check_step ) return false;

    $fields = tangible_fields();
    $previous_num_added = $fields->fetch_value( $fetch_value_name );
    $total_step_added = $previous_num_added + $num_to_add;

    $num_of_steps = [
        'course'    => $fields->fetch_value( 'num_of_courses' ),
        'lesson'    => $fields->fetch_value( 'num_of_lessons' ),
        'topic'     => $fields->fetch_value( 'num_of_topics' ),
        'quiz'      => $fields->fetch_value( 'num_of_quizzes' )
    ];

    $iteration_flag_array = [
        'course'    => 1,
        'lesson'    => 1,
        'topic'     => 1,
        'quiz'      => 1
    ];

    for ( $step_added = 0; $step_added < $num_to_add ; $step_added++ ) { 
        $name = $prefix . ( $previous_num_added + $step_added + 1 );
        if ( $populater->step_exists( $name ) ) {
            continue;
        } else {
            foreach ($iteration_flag_array as $key => $iteration_flag) {
                if ( $iteration_flag > $num_of_steps[$key] ) $iteration_flag_array[$key] = 1;
            }
            $populater->generate_step( $post_type, $num_to_add, $name, $iteration_flag_array, $add_step_to );
            foreach ($iteration_flag_array as $key => $iteration_flag) {
                $iteration_flag_array[$key] ++;
            }
        }
    };
};

// add_step_to is specific to Quiz, because we want to be able to add quiz to course or lesson or topic.
$populater->generate = function ( $num_to_add, $post_type, $add_step_to = '' ) use ( $populater ) {

    $fetch_value_name = '';
    $prefix = '';

    switch ($post_type) {
        case 'course':
            $fetch_value_name = 'num_of_courses';
            $prefix = 'course-';
            break;

        case 'lesson':
            $fetch_value_name = 'num_of_lessons';
            $prefix = 'lesson-';
            break;

        case 'topic':
            $fetch_value_name = 'num_of_topics';
            $prefix = 'topic-';
            break;

        case 'quiz':
            $fetch_value_name = 'num_of_quizzes';
            $prefix = 'quiz-';
            break;

        case 'question':
            $fetch_value_name = 'num_of_questions';
            $prefix = 'question-';
            break;
        
        default:
            break;
    }

    $populater->add_step( $num_to_add, $post_type, $fetch_value_name, $prefix, $add_step_to );
};
