# Tangible Populater

## What is it ? 

Tangible Populater is like [Load Testing Plugin](https://bitbucket.org/tangibleinc/load-testing-plugin/src/master/) but it's used in the context of Tangible Fields pro, specially dynamic values tests. 

You can generate some stuffs with this module. Here a list of what can be generate : 

- Learndash :
    - [Users](#populater-users)
    - [Courses](#populater-courses)
    - [Lessons](#populater-lessons)
    - [Topics](#populater-topics)
    - [Quizzes](#populater-quizzes)
        - [Course](#populater-quizzes-course)
        - [Lesson](#populater-quizzes-lesson) 
        - [Topic](#populater-quizzes-topic) 
        - [Exception](#populater-quizzes-exception) 
    - [Questions](#populater-questions)
    - [Certificates](#populater-certificates)
    - [Assignments](#populater-assignments)
    - [Functions](#populater-functions)
        - [Q&A](#populater-functions-qa)
        - [Course](#populater-functions-course)
        - [Lesson](#populater-functions-lesson)
        - [Topic](#populater-functions-topic)
        - [Quiz](#populater-functions-quiz)
        - [Question](#populater-functions-question)
        - [Certificate](#populater-functions-certificate)
        - [Assignment](#populater-functions-assignment)

## How to use it ? 

The only things you need to know is one function 

```php
generate($num_to_add, $generate_type, $parent_post = '');
```

You need to know that this function return an array of posts. 

Before you go on the next section, let me introduce how works the generate, there is for now, three types of generate : **Standard**, **Specific** and **Exclude** ( we can call them like this ).

#### Standard : 

The standard is when you're going to generate something with just the first two parameters ( num_to_add and generate_type ). 

**For example** : Imagine you create 2 courses and after this 2 courses, you create 4 lessons, each course is gonna have 1 lesson added to them as a step. So in this example, each course will have 2 lessons added ( it start from the first course created ).

#### Specific : 

I'm calling this one specific, it's because, you can use the third argument to specify on which post you want to add your new post. 

**For example** : Imagine you create 2 courses again and after this 2 courses, you also want to create 4 lessons. But for your test, you don't want to have lessons on your second course, so you are gonna use the third argument ( parent_post ) to setup your lessons directly in the chosen course. 

#### Exclude :

Exclude is when you want to create a post without any parents, for course, it's already the case, because you can't have a parent for them. But be carefull when you want to use this type of generation, it will be used for specific cases.

**For example** : Imagine you want to count all the lessons created in the website, not for a related course. You may want to create some lessons not related to a course, because they will be count in the process and it could be good to test it. 

Don't worry, everything is explain in the next step about how to generate for each situation.

So here how to create what you want : 

#### <a id="populater-users"></a>Users : 

```php
generate(1, 'user');
```

It will generate one user, this user is not enrolled in a course or anything ( you need to setup this by yourself in your tests, for the case of TF Pro ).

#### <a id="populater-courses"></a>Courses : 

```php
generate(1, 'course');
```

It will generate one course, this course have all the meta it needs ( you may want some meta that's not setup for now, it will depends of your work ).

#### <a id="populater-lessons"></a>Lessons : 

```php
generate(1, 'lesson');
```

It's gonna create a lesson for the first course created ( be sure there is one ), it's gonna have all the meta needed but as course, you may want to create some if needed. 

```php
generate(1, 'lesson', $parent_name_or_id);
```

It's the same as before but it's gonna create the lesson for a specify course. **parent_name_or_id** can be a string like 'course-1' or a number like 4. 

```php
generate(1, 'lesson', false);
```

It will create a lesson without any parents. It means you don't need to create a course because the lesson will not be related to any course.

#### <a id="populater-topics"></a>Topics : 

```php
generate(1, 'topic');
```

It's gonna create a topic for the first lesson created ( be sure there is one ), it's gonna have all the meta needed but you may want to create some if needed.

```php
generate(1, 'topic', $parent_name_or_id);
```

It's the same as before but it's gonna create the topic for a specify lesson. **parent_name_or_id** can be a string like 'lesson-1' or a number like 4.

```php
generate(1, 'topic', false);
```

It will create a topic without any parents. It means you don't need to create a lesson because the topic will be related to nothing. 

#### <a id="populater-quizzes"></a>Quizzes : 

Quiz is a little bit special, because we need to add the parent post for each generation ( because quiz can be related to course, lesson, topic or nothing ).

**<a id="populater-quizzes-course"></a>Course :** 

```php
generate(1, 'quiz', 'course');
```

It's gonna create a quiz for the first course created ( be sure there is one ), it's gonna have all the meta needed but you may want to create some if needed.

```php
generate(1, 'quiz', [
    'type'          => 'course',
    'name_or_id'    => $parent_name_or_id
]);
```

It's the same as before but it's gonna create the quiz for a specify course, you need to add the 'type' because without this, we can't know on what type of post you want to add your quiz. **parent_name_or_id** can be a string like 'course-1' or a number like 4.

**<a id="populater-quizzes-lesson"></a>Lesson :**

```php
generate(1, 'quiz', 'lesson');
```

It's gonna create a quiz for the first lesson created ( be sure there is one ), it's gonna have all the meta needed but you may want to create some if needed.

```php
generate(1, 'quiz', [
    'type'          => 'lesson',
    'name_or_id'    => $parent_name_or_id
]);
```

It's the same as before but it's gonna create the quiz for a specify lesson, you need to add the 'type' because without this, we can't know on what type of post you want to add your quiz. **parent_name_or_id** can be a string like 'lesson-1' or a number like 4.

**<a id="populater-quizzes-topic"></a>Topic :**

```php
generate(1, 'quiz', 'topic');
```

It's gonna create a quiz for the first topic created ( be sure there is one ), it's gonna have all the meta needed but you may want to create some if needed.

```php
generate(1, 'quiz', [
    'type'          => 'topic',
    'name_or_id'    => $parent_name_or_id
]);
```

It's the same as before but it's gonna create the quiz for a specify topic, you need to add the 'type' because without this, we can't know on what type of post you want to add your quiz. **parent_name_or_id** can be a string like 'topic-1' or a number like 4.

**<a id="populater-quizzes-exception"></a>Exception :**

```php
generate(1, 'quiz', false);
```

This exception is for the cases when you want to create your quiz without any parents. You don't need to create a course / lesson / topic, the quiz will be related to nothing. 

#### <a id="populater-questions"></a>Questions : 

```php
generate(1, 'question');
```

It's gonna create a question for the first quiz created ( be sure there is one ), it's gonna have all the meta needed but you may want to create some if needed.

#### <a id="populater-certificates"></a>Certificates : 

```php
generate(1, 'certificate');
```

It's gonna create a certificate for the first course created ( be sure there is one ), it's gonna have all the meta needed but you may want to create some if needed.

```php
generate(1, 'certificate', $parent_name_or_id);
```

It's the same as before but it's gonna create the certificate for a specify course. **parent_name_or_id** can be a string like 'course-1' or a number like 4.

```php
generate(1, 'certificate', [
  'type'        => 'quiz',
  'name_or_id'  => 1
])
```

With this generate type function, you will be able to create a certificate to a specific quiz only.

```php
generate(1, 'certificate', false);
```

You can create a certificate without any course created. The certificate will be related to nothing. 

#### <a id="populater-assignments"></a>Assignments :

For now, assignment can be create only for lessons. 

```php
generate(1, 'assignment');
```

By default , it's gonna create an assignment to the first lesson created and the author of this assignment gonna be the last user created. 

```php
generate(1, 'assignment', $parent_name_or_id);
```

It's the same as before but it's gonna create the assignment for a specify lesson. **parent_name_or_id** can be a string like 'lesson-1' or a number like 4. The user can't be specify for now, it will only use the last one created. 

#### <a id="populater-functions"></a>Functions : 

You can find functions in some of the generator files. I'll try to explain this functions, how to use them, what they're doing, type by type. 

**<a id="populater-functions-qa"></a>Q&A :**

**Why what I needed is not included in the populater ?** Sometimes, what you need is just to add / update a meta or to call a Learndash function, right now, we don't include all of this, because you can basically do it in your test or where you're using this module. Maybe in the future some functions will be added.  

**Why the function is not working ?** You may want to use a function but it doesn't work. The problem is not always that the function doesn't work, it can be because the function doesn't cover your case.

**<a id="populater-functions-course"></a>Course :**

```php
add_section($course_id, $order, $post_title);
```

This function can add section to a specific course. Each step is in an order in the course. So when you want to add your section, you need to know where you want to add it, because if you add your section at the end, it'll not work. That's why we have the **order** parameter. And the **post_title** parameter is here because each section have a title. 
set_course_status($course_status, $user_id, $course_id);
```

Set the course status for the choosen course. We have 4 possible status : 

- locked.
- open.
- started.
- completed.

You need to add in the parameters the user_id and the course_id. 

**<a id="populater-functions-lesson"></a>Lesson :**

There is nothing for the lessons, all the functions are not meant to be used outside of the populater.

**<a id="populater-functions-topic"></a>Topic :**

There is nothing for the topics, all the functions are not meant to be used outside of the populater.

**<a id="populater-functions-quiz"></a>Quiz :**

```php
complete_quiz($quiz_id_or_name, $user_id, $course_id = 0, $lesson_id = 0, $topic_id = 0);
```

This function is here to complete a specific quiz. You can use a name or id for the quiz. You need the ID of the user, because we need to know which user finished the quiz. ( /!\ Be sure the user you're using is not an admin user if you want to be sure your quiz is completed, because some Learndash function doesn't get result from admin user )

The three others parameters are here just to complete the right quiz, you'll use the parameters depending your situation, if the quiz is related to a course, a lesson or a topic. If you want to complete a lesson quiz, you need to enter the lesson ID **AND** the course ID. For topic it will be the lesson ID **AND** the course ID **AND** the topic ID.

**<a id="populater-functions-question"></a>Question :**

There is nothing for the questions, all the functions are not meant to be used outside of the populater.

**<a id="populater-functions-certificate"></a>Certificate :**

There is nothing for the certificates, all the functions are not meant to be used outside of the populater.

**<a id="populater-functions-assignment"></a>Assignment :**

```php
create_custom_image_locally($assignment_post_title, $file_path);
```

You'll maybe need to create some custom image in your test when you're working with assignments. This function is something that can be edited to add more content in an image if we need. Right now, it's only creating a black background image. 
