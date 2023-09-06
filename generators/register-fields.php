<?php

$fields = tangible_fields();

$fields->register_field( 'num_of_users',
  [
    'store_callback' => tangible_fields()->_store_callbacks['options']('tangible_populater_')['store_callback'],
    'fetch_callback' => tangible_fields()->_store_callbacks['options']('tangible_populater_')['fetch_callback'], 
    'validation_callbacks' => [
      function($name, $value) {
        if( ( (int) $value ) < 0) { return 0; }
        else { return (int) $value; }
      }
    ]
  ]
  +
  tangible_fields()->_permission_callbacks([
    'store' => [ 'user_can', 'manage_options' ],
    'fetch' => [ 'always_allow' ],
  ])
);

$fields->register_field( 'num_of_courses',
  [
    'store_callback' => tangible_fields()->_store_callbacks['options']('tangible_populater_')['store_callback'],
    'fetch_callback' => tangible_fields()->_store_callbacks['options']('tangible_populater_')['fetch_callback'], 
    'validation_callbacks' => [
      function($name, $value) {
        if( ( (int) $value ) < 0) { return 0; }
        else { return (int) $value; }
      }
    ]
  ]
  +
  tangible_fields()->_permission_callbacks([
    'store' => [ 'user_can', 'manage_options' ],
    'fetch' => [ 'always_allow' ],
  ])
);

$fields->register_field( 'num_of_lessons',
  [
    'store_callback' => tangible_fields()->_store_callbacks['options']('tangible_populater_')['store_callback'],
    'fetch_callback' => tangible_fields()->_store_callbacks['options']('tangible_populater_')['fetch_callback'], 
    'validation_callbacks' => [
      function($name, $value) {
        if( ( (int) $value ) < 0) { return 0; }
        else { return (int) $value; }
      }
    ]
  ]
  +
  tangible_fields()->_permission_callbacks([
    'store' => [ 'user_can', 'manage_options' ],
    'fetch' => [ 'always_allow' ],
  ])
);

$fields->register_field( 'num_of_topics',
  [
    'store_callback' => tangible_fields()->_store_callbacks['options']('tangible_populater_')['store_callback'],
    'fetch_callback' => tangible_fields()->_store_callbacks['options']('tangible_populater_')['fetch_callback'], 
    'validation_callbacks' => [
      function($name, $value) {
        if( ( (int) $value ) < 0) { return 0; }
        else { return (int) $value; }
      }
    ]
  ]
  +
  tangible_fields()->_permission_callbacks([
    'store' => [ 'user_can', 'manage_options' ],
    'fetch' => [ 'always_allow' ],
  ])
);

$fields->register_field( 'num_of_quizzes',
  [
    'store_callback' => tangible_fields()->_store_callbacks['options']('tangible_populater_')['store_callback'],
    'fetch_callback' => tangible_fields()->_store_callbacks['options']('tangible_populater_')['fetch_callback'], 
    'validation_callbacks' => [
      function($name, $value) {
        if( ( (int) $value ) < 0) { return 0; }
        else { return (int) $value; }
      }
    ]
  ]
  +
  tangible_fields()->_permission_callbacks([
    'store' => [ 'user_can', 'manage_options' ],
    'fetch' => [ 'always_allow' ],
  ])
);

$fields->register_field( 'num_of_questions',
  [
    'store_callback' => tangible_fields()->_store_callbacks['options']('tangible_populater_')['store_callback'],
    'fetch_callback' => tangible_fields()->_store_callbacks['options']('tangible_populater_')['fetch_callback'], 
    'validation_callbacks' => [
      function($name, $value) {
        if( ( (int) $value ) < 0) { return 0; }
        else { return (int) $value; }
      }
    ]
  ]
  +
  tangible_fields()->_permission_callbacks([
    'store' => [ 'user_can', 'manage_options' ],
    'fetch' => [ 'always_allow' ],
  ])
);

$fields->register_field( 'num_of_certificates',
  [
    'store_callback' => tangible_fields()->_store_callbacks['options']('tangible_populater_')['store_callback'],
    'fetch_callback' => tangible_fields()->_store_callbacks['options']('tangible_populater_')['fetch_callback'], 
    'validation_callbacks' => [
      function($name, $value) {
        if( ( (int) $value ) < 0) { return 0; }
        else { return (int) $value; }
      }
    ]
  ]
  +
  tangible_fields()->_permission_callbacks([
    'store' => [ 'user_can', 'manage_options' ],
    'fetch' => [ 'always_allow' ],
  ])
);