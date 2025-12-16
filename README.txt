=== UniLMS ===
Contributors: (this should be a list of wordpress.org userid's)
Donate link: http://codoplex.weebly.com
Tags: lms, learning management system, university management
Requires at least: 3.0.1
Tested up to: 4.8.1
Stable tag: 4.8.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A learning management system developed for universities, schools, colleges, academies or any other type of institutes.

== Description ==

Features List:

1. Teachers / Faculty Members Module:
	* Teacher registration page is automatically created when UniLMS plugin is activated
	* Teacher can edit his/her profile by logging in to the backend of website and visiting profile menu
	* All teachers/faculty members list page is automatically created when UniLMS plugin is activated
	* Individual teacher/faculty member profile page is also automatically created when UniLMS plugin is activated
	* Teacher can add/edit contact and social media information by visiting profile page in backend of website
	* Public profile page shows teacher's contact and social media details as well as a list of courses assigned to the teacher
	* Teacher can create/edit new courses, lectures, activities(quizzes, assignments, mid term exam, final term exam, projects and add marks for these activites), questions, attendances, course files and students
	* Teacher can print out all activites, courses, lectures or course files by visiting the public pages of them
	* Admin can also add teachers manually
	* Admin has complete access to all the content created by the teacher
	* Admin can assign a course to a particular teacher
	* When a teacher registers to the website, then he/she cannot login to the website until admin approves it
	* Content created by teachers is not published until admin reviews it
2. Students Module:
	* Students can register to the website as a standard user
	* Admin can approve student profile by reviewing it and assigning him UniLMS Student role by editing his/her profile
	* Once a student is assigned UniLMS Student role, then he/she can login to the website and add/edit details like department, class, registration number etc. by visiting profile page in the backend of website
	* After adding details in profile, user can visit Student Dashboard page which is automatically created when UniLMS plugin is activated
	* At Student Dashboard page, student can see his/her information and also he/she can view results of all activities (quizzes, assignments, mid term, final term, final result etc.)
	* Admin or any teacher can add students manually
	* Students added by teachers are reviewed by admin
	* Each student is assigned to particular class
3. Classes Module:
	* Admin can add new classes or update/delete esisting ones
	* Admin can assign courses to each class
	* Admin can also generate class specific time table from complete time table
	* Classes archive and single pages can also be viewed from front end of the website
	* Classes archive page lists all classes with class details like (session, semester, fall/spring)
	* Single class page shows complete details of that class with class specific time table
	* Any student can view each class details from front end of website
	* Classes can be duplicated if they share most of the content
4. Courses Module:
	* Admin as well as teachers can create new courses
	* Courses created by teachers are not published until reviewed by admin
	* Admin can edit/delete all courses while teachers can only edit their own courses
	* Teachers cannot even delete their own courses
	* When UniLMS plugin is activated, then a new page titled UNILMS Courses is automatically created which lists all courses in a tabular form
	* Courses can be duplicated if they share most of the content
	* Each course is assigned to a class and a teacher
	* Course contents, of the course created by teacher, are generated using the lectures and activities created by the teacher
	* Course author can define sessional marks %, mid term exam %, final term exam % and grad policy etc.
	* Course author can generate course specific time table from complete time table
	* Course archive and single pages can be viewed publically from front end of the website
	* Course archive page lists all courses
	* Course single page shows all details of course like course description, course contents and course specific time table
5. Lectures Module:
	* Admin as well as teachers can create new lectures while teachers can only edit their own lectures
	* These lectures can be added to the course contents of the course
	* Each lecture is assigned to particular course
	* Teacher can share all necessary details or resource materials with each lecture
6. Activities Module:
	* Activities include quizzes, assignments, mid term exam, final term exam, projects, class participation etc.
	* Teacher can assign questions to activities like quizzes, mid term exam or final term exam
	* Teacher can select whether this activity will count in sessionals marks or not
	* Each activity's marks can be added for each student
	* These activities can be viewed publically except the fact that questions will not be visible publically. On public pages of these activities, details like activity max marks, submission date, or activity result is shown
7. Questions Module:
	* Both admin and teachers can create new questions while teachers can only edit/use their own questions while preparing quizzes
	* Mcqs, true/false, short questions, and long questions are the options available as question type
	* Max marks and correct option can also be defined for questions of type mcqs or true/false
8. Attendances Module:
	* Both admin and teachers can create new attendance while teachers can only edit/use their own attendances while preparing course files
	* Attendance date, class, course, activity and students list to mark attendance are some of the options available
	* Attendances are used while preparing course files or to give attendance marks to the students
9. Course Files Module:
	* Course files includes grading policy, course contents, course plan, instructor log, student log, quizzes, assignments, sessionals, mid term exam, final term exam, attendance sheet and final result of that course
	* Each course file part is automatically generated by specifying class and course
	* Each course file can also be seen publically on front side of the website
	* Archive page and single page of each course file are publically visible to anyone
	* Teacher can printout each part of course file from admin or front end side of the website
10. Time Tables Module:
	* Admin can generate random time table automatically
	* Time table can be generated from courses, classes and faculty members added inside UniLMS plugin
	* Admin can also generate custom time table for custom courses, classes and faculty members
	* Time slots, day slots and room slots are defined for each time table
	* Time table also shows empty slots which can be used to arrange supplementary classes
	* Time tables can also be shown publically so that students can see their time table by visiting website
11. Settings Module:
	Admin can define institute logo which can be used while printing course files or any other documents

== Installation ==

This section describes how to install the plugin and get it working.

1. Upload `uni-lms` folder to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. A new menu will be created named as UniLMS in WordPress sidebar 

== Frequently Asked Questions ==


== Screenshots ==


== Changelog ==

= 1.0.0 =
* Default version of the plugin

`<?php code(); // goes in backticks ?>`