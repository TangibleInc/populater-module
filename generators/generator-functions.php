<?php 

// This file concern every functions to generate posts. 

function check_parent_posts_exist( $generate_type , $parent_post = '' ) {

    if ( empty($generate_type) ) return false;
    $fields = tangible_fields();
    global $wpdb;

    if ( !empty($parent_post) && $parent_post !== false && $generate_type !== 'quiz' && $generate_type !== 'certificate' ) {
        if ( is_numeric($parent_post) ) {
            $post = get_post($parent_post);
            if ( empty($post) ) return false;
        } else {
            $parent_id = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $parent_post . "'"  );
            if ( empty($parent_id) ) return false;
        }
    }

    $check = true;
    switch ( $generate_type ) {
        case 'assignment' :
            $num_of_courses = $fields->fetch_value( 'num_of_courses' );
            $num_of_users = $fields->fetch_value( 'num_of_users' );
            if ( $num_of_courses == 0 && $parent_post !== false ) $check = false;
            if ( $num_of_users == 0 && $parent_post !== false ) $check = false;
            break;

        case 'certificate':
            $num_of_courses = $fields->fetch_value( 'num_of_courses' );
            if ( $num_of_courses == 0 && $parent_post !== false ) $check = false;
            break;

        case 'course':
            $check = true;
            break;

        case 'lesson':
            $num_of_courses = $fields->fetch_value( 'num_of_courses' );
            if ( $num_of_courses == 0 && $parent_post !== false ) $check = false;
            break;

        case 'topic':
            $num_of_courses = $fields->fetch_value( 'num_of_courses' );
            $num_of_lessons = $fields->fetch_value( 'num_of_lessons' );
            if ( ( $num_of_courses == 0 || $num_of_lessons == 0 ) && $parent_post !== false ) $check = false;
            break;

        case 'quiz': 
            if ( $parent_post = 'course' ) {
                $num_of_courses = $fields->fetch_value( 'num_of_courses' );
                if ( $num_of_courses == 0 ) $check = false;
            } else if ( $parent_post = 'lesson' ) {
                $num_of_lessons = $fields->fetch_value( 'num_of_lessons' );
                if ( $num_of_lessons == 0 ) $check = false;
            } else if ( $parent_post = 'topic' ) {
                $num_of_topics = $fields->fetch_value( 'num_of_topics' );
                if ( $num_of_topics == 0 ) $check = false; 
            } else {
                $check = false;
            }
            if ( $parent_post === false ) $check = true;
            if ( is_array($parent_post) ) {
                $parent_post = $parent_post['name_or_id'];
                if ( is_numeric($parent_post) ) {
                    $post = get_post($parent_post);
                    if ( empty($post) ) $check = false;
                }   else {
                    $parent_id = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . $parent_post . "'"  );
                    if ( empty($parent_id) ) $check = false;
                }
            }
            break;

        case 'question':
            $num_of_quizzes = $fields->fetch_value( 'num_of_quizzes' );
            if ( $num_of_quizzes == 0 && $parent_post !== false ) $check = false;
            break;
        
        default:
            $check = false;
            break;
    };

    return $check;
};

function generate_post( $generate_type, $name, $iteration_flag_array, $parent_post = '' ) {
    
    $populater = populater();
    $fields = tangible_fields();
    switch ( $generate_type ) {
        case 'assignment' :
            return generate_assignment( $name, $iteration_flag_array['lesson'], $fields->fetch_value( 'num_of_users' ), $parent_post );

        case 'certificate':
            return generate_certificate( $name, $iteration_flag_array['course'], $iteration_flag_array['quiz'], $parent_post );

        case 'course':
            return generate_course( $name );

        case 'lesson': 
            return generate_lesson( $name, $iteration_flag_array['course'], $parent_post );

        case 'topic':
            return generate_topic( $name, $iteration_flag_array['course'], $iteration_flag_array['lesson'], $parent_post );

        case 'quiz':
            return generate_quiz( $name, $iteration_flag_array['course'], $iteration_flag_array['lesson'], $iteration_flag_array['topic'], $parent_post );

        case 'question':
            return generate_question( $name, $iteration_flag_array['quiz'], $parent_post );
        
        default:
            return false;
    }

};

function generate_posts( $num_to_add, $generate_type, $fetch_value_name, $prefix, $parent_post = '' ) { 

    $posts = [];

    if ( $num_to_add == 0 ) return $posts;
    if ( $num_to_add < 0 ) return false;

    $populater = populater();
    $check_parent_posts_exist = check_parent_posts_exist($generate_type, $parent_post);
    if ( !$check_parent_posts_exist ) return false;

    $fields = tangible_fields();
    $previous_num_added = is_wp_error( $fields->fetch_value( $fetch_value_name ) ) ? 1 : $fields->fetch_value( $fetch_value_name );

    $num_of_posts = [
        'course'        => $fields->fetch_value( 'num_of_courses' ),
        'lesson'        => $fields->fetch_value( 'num_of_lessons' ),
        'topic'         => $fields->fetch_value( 'num_of_topics' ),
        'quiz'          => $fields->fetch_value( 'num_of_quizzes' ),
        'certificate'   => $fields->fetch_value( 'num_of_certificates' ),
        'assignment'    => $fields->fetch_value( 'num_of_assignments' )
    ];

    $iteration_flag_array = [
        'course'        => 1,
        'lesson'        => 1,
        'topic'         => 1,
        'quiz'          => 1,
        'certificate'   => 1,
        'assignment'    => 1
    ];

    for ( $post_added = 0; $post_added < $num_to_add ; $post_added++ ) { 
        $name = $prefix . ( $previous_num_added + $post_added + 1 );

        if ( $populater->check_generated_name_exists( $name ) ) {
            continue;
        } else {
            foreach ($iteration_flag_array as $key => $iteration_flag) {
                if ( $iteration_flag > $num_of_posts[$key] ) $iteration_flag_array[$key] = 1;
            }
            $posts []= generate_post( $generate_type, $name, $iteration_flag_array, $parent_post );
            foreach ($iteration_flag_array as $key => $iteration_flag) {
                $iteration_flag_array[$key] ++;
            }
        }
    };

    $total_num_added = $previous_num_added + $num_to_add;
    $fields->store_value( $fetch_value_name, $total_num_added );

    return $posts;
};

function generate_users( $num_to_add ) {

    $users = [];

    if( $num_to_add == 0 ) return $users;
    if( $num_to_add < 0 ) return false;

    $fields = tangible_fields();
    $previous_num_of_users = $fields->fetch_value( 'num_of_users' );

    for( $added_users = 0; $added_users < $num_to_add; $added_users++ ) { 
        $username = 'user-' . ( $previous_num_of_users + $added_users + 1 );
        if( username_exists( $username  ) ) {
          continue;
        } else {
          $users []= generate_user( ( $previous_num_of_users + $added_users + 1 ) );
        }
    }

    $total_num_of_users = $previous_num_of_users + $num_to_add;
    $fields->store_value( 'num_of_users', $total_num_of_users );

    return $users; 
};

// parent_post is specific to Quiz, because we want to be able to add quiz to course or lesson or topic.
$populater->generate = function ( $num_to_add, $generate_type, $parent_post = '' ) use ( $populater ) {

    $fetch_value_name = '';
    $prefix = '';

    if ( $generate_type === 'user' ) {
        return generate_users( $num_to_add );
    } else {
        switch ($generate_type) {

            case 'assignment' :
                $fetch_value_name = 'num_of_assignments';
                $prefix = 'assignment-';
                break;

            case 'certificate':
                $fetch_value_name = 'num_of_certificates';
                $prefix = 'certificate-';
                break;

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
    
        return generate_posts( $num_to_add, $generate_type, $fetch_value_name, $prefix, $parent_post );
    }
};
