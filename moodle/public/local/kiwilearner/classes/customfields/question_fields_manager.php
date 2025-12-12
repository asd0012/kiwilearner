<?php
// local/kiwilearner/classes/customfields/question_fields_manager.php

namespace local_kiwilearner\customfields;

defined('MOODLE_INTERNAL') || die();

use core_component;
use core_customfield\api;
use core_customfield\category_controller;
use core_customfield\field_controller;
use qbank_customfields\customfield\question_handler;

/**
 * Helper for ensuring KiwiLearner question custom fields exist.
 */
class question_fields_manager {

    /** Name of the category shown in Question custom fields admin UI. */
    public const CATEGORY_NAME = 'KiwiLearner XP';

    /** Shortname constants so you can reuse them elsewhere. */
    public const FIELD_XP_PARTICIPATION = 'kiwi_xp_participation';
    public const FIELD_XP_CORRECT       = 'kiwi_xp_correct';
    public const FIELD_XP_ENABLED       = 'kiwi_xp_enabled';

    /**
     * Ensure the KiwiLearner question custom fields exist.
     *
     * Safe to call multiple times – it only creates missing bits.
     */
    public static function ensure_fields_exist(): void {
        global $CFG;

        // 1. Check the qbank_customfields plugin exists (Moodle 4.x core).
        if (!core_component::get_plugin_directory('qbank', 'customfields')) {
            // Optional: debugging('qbank_customfields plugin not found; skipping KiwiLearner XP fields.');
            return;
        }

        // 2. Get the question custom field handler.
        //    itemid is always 0 for questions. :contentReference[oaicite:3]{index=3}
        $handler = question_handler::create();

        // 3. Find (or create) the KiwiLearner XP category.
        $category = self::get_or_create_category($handler);

        // 4. Build an index of existing fields in this category by shortname.
        $existing = [];
        foreach ($category->get_fields() as $field) { // :contentReference[oaicite:4]{index=4}
            $existing[$field->get('shortname')] = $field;
        }

        // 5. Ensure each field exists.
        if (!isset($existing[self::FIELD_XP_PARTICIPATION])) {
            self::create_number_field(
                $category,
                self::FIELD_XP_PARTICIPATION,
                'Kiwi XP – participation'
            );
        }

        if (!isset($existing[self::FIELD_XP_CORRECT])) {
            self::create_number_field(
                $category,
                self::FIELD_XP_CORRECT,
                'Kiwi XP – correct answer'
            );
        }

        if (!isset($existing[self::FIELD_XP_ENABLED])) {
            self::create_checkbox_field(
                $category,
                self::FIELD_XP_ENABLED,
                'Kiwi XP – enabled'
            );
        }
    }

    /**
     * Get the KiwiLearner XP category or create it if missing.
     */
    protected static function get_or_create_category(question_handler $handler): category_controller {
        // Existing categories with fields. :contentReference[oaicite:5]{index=5}
        $categories = $handler->get_categories_with_fields();

        foreach ($categories as $category) {
            if ($category->get('name') === self::CATEGORY_NAME) {
                return $category;
            }
        }

        // No category yet – create one.
        // create_category() returns the category id. :contentReference[oaicite:6]{index=6}
        $catid = $handler->create_category(self::CATEGORY_NAME);

        // Turn that id into a category_controller instance. :contentReference[oaicite:7]{index=7}
        return category_controller::create($catid);
    }

    /**
     * Create a "Number" question custom field in the given category.
     */
    protected static function create_number_field(
        category_controller $category,
        string $shortname,
        string $name
    ): void {
        // 'number' here refers to the core customfield_number plugin. :contentReference[oaicite:8]{index=8}
        $field = field_controller::create(
            0,
            (object)['type' => 'number'],
            $category
        );

        $field->set('shortname', $shortname);
        $field->set('name', $name);
        $field->set('description', '');
        $field->set('descriptionformat', FORMAT_HTML);

        // Minimal configdata – no default value required.
        $configdata = new \stdClass();

        api::save_field_configuration($field, $configdata); // :contentReference[oaicite:9]{index=9}
    }

    /**
     * Create a "Checkbox" question custom field in the given category.
     */
    protected static function create_checkbox_field(
        category_controller $category,
        string $shortname,
        string $name
    ): void {
        $field = field_controller::create(
            0,
            (object)['type' => 'checkbox'],
            $category
        );

        $field->set('shortname', $shortname);
        $field->set('name', $name);
        $field->set('description', '');
        $field->set('descriptionformat', FORMAT_HTML);

        $configdata = new \stdClass();
        // For checkbox, a configdata->defaultvalue could be set, but we leave it to admin.

        api::save_field_configuration($field, $configdata);
    }
}
