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
    - [Questions](#populater-questions)
    - [Certificates](#populater-certificates)

## How to use it ? 

The only things you need to know is one function 

```php
generate($num_to_add, $generate_type, $parent_post = '');
```

You need to know that this function return an array of posts. 

Before you go on the next section, let me introduce how works the generate, there is for now, two types of generate, the standard one and the specific ( we can call them like this ).

#### Standard : 

The standard is when you're going to generate something with just the first two parameters ( num_to_add and generate_type ). 

**For example** : Imagine you create 2 courses and after this 2 courses, you create 4 lessons, each course is gonna have 1 lesson added to them as a step. So in this example, each course will have 2 lessons added ( it start from the first course created ).

#### Specific : 

I'm calling this one specific, it's because, you can use the third argument to specify on which post you want to add your new post. 

**For example** : Imagine you create 2 courses again and after this 2 courses, you also want to create 4 lessons. But for your test, you don't want to have lessons on your second course, so you are gonna use the third argument ( parent_post ) to setup your lessons directly in the chosen course. 

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

#### <a id="populater-topics"></a>Topics : 

```php
generate(1, 'topic');
```

It's gonna create a topic for the first lesson created ( be sure there is one ), it's gonna have all the meta needed but you may want to create some if needed.

```php
generate(1, 'topic', $parent_name_or_id);
```

It's the same as before but it's gonna create the topic for a specify lesson. **parent_name_or_id** can be a string like 'lesson-1' or a number like 4.

#### <a id="populater-quizzes"></a>Quizzes : 

Quiz is a little bit special, because we need to add the parent post for each generation ( because quiz can be related to course, lesson, topic ).

**Course :** 

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

**Lesson :**

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

**Topic :**

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
