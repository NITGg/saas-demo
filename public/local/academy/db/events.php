<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Event observers for local_academy.
 */
$observers = [
    [
        // BUG-047: a teacher's question is saved without script (Moodle shows questions uncleaned).
        'eventname' => '\core\event\question_created',
        'callback'  => '\local_academy\local\question_cleaner::question_created',
    ],
    // BUG-047: the rest of the teacher content Moodle shows uncleaned (see content_cleaner).
    ['eventname' => '\core\event\course_module_created', 'callback' => '\local_academy\local\content_cleaner::module_saved'],
    ['eventname' => '\core\event\course_module_updated', 'callback' => '\local_academy\local\content_cleaner::module_saved'],
    ['eventname' => '\core\event\course_section_updated', 'callback' => '\local_academy\local\content_cleaner::section_saved'],
    ['eventname' => '\core\event\course_created', 'callback' => '\local_academy\local\content_cleaner::course_saved'],
    ['eventname' => '\core\event\course_updated', 'callback' => '\local_academy\local\content_cleaner::course_saved'],
    ['eventname' => '\core\event\course_restored', 'callback' => '\local_academy\local\content_cleaner::course_restored'],
    ['eventname' => '\mod_book\event\chapter_created', 'callback' => '\local_academy\local\content_cleaner::chapter_saved'],
    ['eventname' => '\mod_book\event\chapter_updated', 'callback' => '\local_academy\local\content_cleaner::chapter_saved'],
    ['eventname' => '\mod_lesson\event\page_created', 'callback' => '\local_academy\local\content_cleaner::lesson_page_saved'],
    ['eventname' => '\mod_lesson\event\page_updated', 'callback' => '\local_academy\local\content_cleaner::lesson_page_saved'],
    ['eventname' => '\mod_lesson\event\essay_assessed', 'callback' => '\local_academy\local\content_cleaner::essay_assessed'],
    ['eventname' => '\mod_data\event\template_updated',
        'callback' => '\local_academy\local\content_cleaner::data_templates_saved'],
    ['eventname' => '\mod_quiz\event\question_manually_graded',
        'callback' => '\local_academy\local\content_cleaner::quiz_comment_saved'],
    // There is no core "user confirmed" event (auth_email just sets confirmed=1),
    // so we use the user's first successful login as the "just confirmed" signal
    // and send the welcome message once.
    [
        'eventname' => '\core\event\user_loggedin',
        'callback'  => '\local_academy\observer::user_loggedin',
    ],
    // New courses default to a TOMORROW start date (core), which hides their
    // content until then — reset it to today so the course is usable immediately.
    [
        'eventname' => '\core\event\course_created',
        'callback'  => '\local_academy\observer::course_created',
    ],
    // Years are the course categories: keep the student "Year" dropdown in step.
    [
        'eventname' => '\core\event\course_category_created',
        'callback'  => '\local_academy\observer::category_changed',
    ],
    [
        'eventname' => '\core\event\course_category_updated',
        'callback'  => '\local_academy\observer::category_changed',
    ],
    [
        'eventname' => '\core\event\course_category_deleted',
        'callback'  => '\local_academy\observer::category_changed',
    ],
];
