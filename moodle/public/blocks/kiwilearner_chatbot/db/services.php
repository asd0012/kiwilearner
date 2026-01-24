<?php
defined('MOODLE_INTERNAL') || die();

$functions = [
    'block_kiwilearner_chatbot_get_context_cards' => [
        'classname'   => 'block_kiwilearner_chatbot\external',
        'methodname'  => 'get_context_cards',
        'description' => 'Greeting + deadlines next 7 days + course updates last 7 days',
        'type'        => 'read',
        'ajax'        => true,
    ],
    'block_kiwilearner_chatbot_detect_resource_context' => [
        'classname'   => 'block_kiwilearner_chatbot\external',
        'methodname'  => 'detect_resource_context',
        'description' => 'Detect whether the current activity is a resource PDF and return basic context',
        'type'        => 'read',
        'ajax'        => true,
    ],
    'block_kiwilearner_chatbot_get_pdf_survey' => [
        'classname'   => 'block_kiwilearner_chatbot\external',
        'methodname'  => 'get_pdf_survey',
        'description' => 'Get current user survey state/answers for a cmid',
        'type'        => 'read',
        'ajax'        => true,
    ],

    'block_kiwilearner_chatbot_save_pdf_survey' => [
        'classname'   => 'block_kiwilearner_chatbot\external',
        'methodname'  => 'save_pdf_survey',
        'description' => 'Save/update current user survey state/answers for a cmid',
        'type'        => 'write',
        'ajax'        => true,
    ],

    'block_kiwilearner_chatbot_evaluate_pdf_takeaways' => [
        'classname'   => 'block_kiwilearner_chatbot\external',
        'methodname'  => 'evaluate_pdf_takeaways',
        'description' => 'Use Moodle AI to evaluate student takeaways vs PDF text',
        'type'        => 'read',
        'ajax'        => true,
    ],

    'block_kiwilearner_chatbot_cache_pdf_text' => [
        'classname'   => 'block_kiwilearner_chatbot\external',
        'methodname'  => 'cache_pdf_text',
        'description' => 'Extract PDF text for a resource and cache it in DB',
        'type'        => 'read',
        'ajax'        => true,
    ],


];
