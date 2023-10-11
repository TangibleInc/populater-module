# Tangible Populater Dev Doc

## What is it ? 

You're a dev and you need to work on this module. You don't understand how it works, what does which file and it's normal, I'll try to explain here, how everything works. 

Before I start, the type of code here can be drastically different from others project. I don't think this type of code is better than others. it's more like a pyramid here, one function is called by you and this function call others for small jobs. 

I can explain line by line what's going on, you'll need to discover by yourself if you need to work on this.

## Improve Tangible Populater

If you are here because you want to add some code to the module, you can understand how each file works here. 

When you want to add something think : "Can my code be used by just one type of generator, multiple or all of them ?" "Is my function ( or some part of it ) unique for one case or can be a global function ?". 

- **All files :** 
    - [generator-functions.php](#generator-functions)
    - [global-functions.php](#global-functions)
    - [register-fields.php](#register-fields)
    - [specific generator](#specific-generator)

First of all, there is one file you need to understand, because it's the main. 

### <a id=generator-functions ></a>generator-functions.php :

This file contains all the function used by the generator ( and the generator only ).

The main function is at the end of the file, why ? you're gonna ask, I don't really know, it's how I code so... Do as you want !

**generate :**

In this file, you need to go to the generator function. This function is the one you call when you want to generate some posts / users. **Important** : it's the only function you need for generate, it's coded to handle everything. 

**generate_posts :**

Another thing you may not understand in this file is, why do we have generate_posts calling generate_post.

This part is for readability, if you check where generate_post is called, you'll see it's between 2 foreach, and generate_post use a switch, now imagine this switch between the 2 foreach, it'll be a huge mess. 

**generate_users :**

For generate_users it's the same, but generate_user is not in this file because right now we handle just one type of users. Maybe if we add the possibility to add a specific type of users, we'll maybe need to use the same system as generate_posts. 

**check_parent_posts_exist :**

This function is here because it needs to be used only by the generator. It's gonna checked if the parent post exist, like if you want to create a lesson but there is no course created, it'll not create your lesson. 

### <a id=global-functions ></a>global-functions.php : 

I don't have more to say about this file, it's when you want to add a function that can be used by all generator or a function that can be used by you outside the module for example. 

**get_all_posts :**

You have the perfect example here, it's not meant to be used in populater, but by you. If you want to get all the lessons created, you can use this. 

### <a id=register-fields ></a>register-fields.php : 

You may not know, but this module use fields, we need to keep how many posts / users we created, so it's handle by fields. For each post type and user, we have a field created. You can check them all here. 

Be sure if you want to create a new post type or anything else to be sure that the field you want to use is created. 

### <a id=specific-generator ></a>specific generator : 

**Generate :**

I'll handle all the others files here because most of them do the same thing. Specific generator files is here for function that can't be used by others generator. 

If you check all the generators, you'll see a generate function. That's the function who's gonna create the post / user. 

**Remove :**

You can also see there is a remove function. For each cases, you need to remove a lot of meta, and they're different for each post type / user. 

**Others :**

Then we have the functions specific to add meta for example. Like quiz who need a quiz_pro_id, a course_id, lesson_id ( which is used for topic_id too ) depending the context. You can also see that we add the posts to the course steps. 

We also have the functions specific for a context like in quiz we have complete_quiz. Because it needs a bunch of things to work, we created this function so it's more easy to use and do. 