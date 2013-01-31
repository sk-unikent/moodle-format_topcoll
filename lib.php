<?php

/**
 * Collapsed Topics Information
 *
 * A topic based format that solves the issue of the 'Scroll of Death' when a course has many topics. All topics
 * except zero have a toggle that displays that topic. One or more topics can be displayed at any given time.
 * Toggles are persistent on a per browser session per course basis but can be made to persist longer by a small
 * code change. Full installation instructions, code adaptions and credits are included in the 'Readme.txt' file.
 *
 * @package    course/format
 * @subpackage topcoll
 * @version    See the value of '$plugin->version' in version.php.
 * @copyright  &copy; 2012-onwards G J Barnard in respect to modifications of standard topics format.
 * @author     G J Barnard - gjbarnard at gmail dot com and {@link http://moodle.org/user/profile.php?id=442195}
 * @author     Based on code originally written by Dan Poltawski.
 * @link       http://docs.moodle.org/en/Collapsed_Topics_course_format
 * @license    http://www.gnu.org/copyleft/gpl.html GNU Public License
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.

 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.

 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
require_once($CFG->dirroot . '/course/format/topcoll/tcconfig.php'); // For Collaped Topics defaults.
require_once($CFG->dirroot . '/course/format/lib.php'); // For format_base.

class format_topcoll extends format_base {

    private $settings;

    /**
     * Returns the format's settings and gets them if they do not exist.
     * @return type The settings as an array.
     */
    public function get_settings() {
        if (empty($this->settings) == true) {
            $this->settings = $this->get_format_options();
        }
        return $this->settings;
    }

    /**
     * Indicates this format uses sections.
     *
     * @return bool Returns true
     */
    public function uses_sections() {
        return true;
    }

    /**
     * Gets the name for the provided section.
     *
     * @param stdClass $section The section.
     * @return string The section name.
     */
    public function get_section_name($section) {
        $course = $this->get_course();
        $section = $this->get_section($section);
        $o = '';
        $tcsettings = $this->get_settings();
        $coursecontext = context_course::instance($course->id);

        // We can't add a node without any text
        if ((string) $section->name !== '') {
            $o .= format_string($section->name, true, array('context' => $coursecontext));
        } else if ($section->section == 0) {
            $o = get_string('section0name', 'format_topcoll');
        } else {
            if (($tcsettings['layoutstructure'] == 1) || ($tcsettings['layoutstructure'] == 4)) {
                $o = get_string('sectionname', 'format_topcoll') . ' ' . $section->section;
            } else {
                $o = $this->get_section_dates($section, $course, $tcsettings);
            }
        }

// Now done here so that the drag and drop titles will be the correct strings as swapped in format.js.
// But only if we are using toggles which will be if all sections are on one page or we are editing the main page
// when in one section per page which is coded in 'renderer.php/print_multiple_section_page()' when it calls 'section_header()'
// as that gets called from 'format.php' when there is no entry for '$displaysetting' - confused? I was, took ages to figure.
        if (($course->coursedisplay == COURSE_DISPLAY_SINGLEPAGE) && ($section->section != 0)) {
            switch ($tcsettings['layoutelement']) {
                case 1:
                case 2:
                case 3:
                case 4:
                    $o .= ' - ' . get_string('topcolltoggle', 'format_topcoll'); // The word 'Toggle'.
                    break;
            }
        }

        return $o;
    }

    public function get_section_dates($section, $course, $tcsettings) {
        $dateformat = ' ' . get_string('strftimedateshort');
        $o = '';
        if ($tcsettings['layoutstructure'] == 5) {
            $day = $this->format_topcoll_get_section_day($section, $course);

            $weekday = userdate($day, $dateformat);
            $o = $weekday;
        } else {
            $dates = $this->format_topcoll_get_section_dates($section, $course);

            // We subtract 24 hours for display purposes.
            $dates->end = ($dates->end - 86400);

            $weekday = userdate($dates->start, $dateformat);
            $endweekday = userdate($dates->end, $dateformat);
            $o = $weekday . ' - ' . $endweekday;
        }
        return $o;
    }

    /**
     * The URL to use for the specified course (with section)
     *
     * @param int|stdClass $section Section object from database or just field course_sections.section
     *     if omitted the course view page is returned
     * @param array $options options for view URL. At the moment core uses:
     *     'navigation' (bool) if true and section has no separate page, the function returns null
     *     'sr' (int) used by multipage formats to specify to which section to return
     * @return null|moodle_url
     */
    public function get_view_url($section, $options = array()) {
        $course = $this->get_course();
        $url = new moodle_url('/course/view.php', array('id' => $course->id));

        $sr = null;
        if (array_key_exists('sr', $options)) {
            $sr = $options['sr'];
        }
        if (is_object($section)) {
            $sectionno = $section->section;
        } else {
            $sectionno = $section;
        }
        if ($sectionno !== null) {
            if ($sr !== null) {
                if ($sr) {
                    $usercoursedisplay = COURSE_DISPLAY_MULTIPAGE;
                    $sectionno = $sr;
                } else {
                    $usercoursedisplay = COURSE_DISPLAY_SINGLEPAGE;
                }
            } else {
                $usercoursedisplay = $course->coursedisplay;
            }
            if ($sectionno != 0 && $usercoursedisplay == COURSE_DISPLAY_MULTIPAGE) {
                $url->param('section', $sectionno);
            } else {
                if (!empty($options['navigation'])) {
                    return null;
                }
                $url->set_anchor('section-' . $sectionno);
            }
        }
        return $url;
    }

    /**
     * Returns the information about the ajax support in the given source format
     *
     * The returned object's property (boolean)capable indicates that
     * the course format supports Moodle course ajax features.
     * The property (array)testedbrowsers can be used as a parameter for {@link ajaxenabled()}.
     *
     * @return stdClass
     */
    public function supports_ajax() {
        $ajaxsupport = new stdClass();
        $ajaxsupport->capable = true;
        $ajaxsupport->testedbrowsers = array('MSIE' => 8.0, 'Gecko' => 20061111, 'Opera' => 9.0, 'Safari' => 531, 'Chrome' => 6.0);
        return $ajaxsupport;
    }

    /**
     * Custom action after section has been moved in AJAX mode
     *
     * Used in course/rest.php
     *
     * @return array This will be passed in ajax respose
     */
    function ajax_section_move() {
        global $PAGE;
        $titles = array();
        $course = $this->get_course();
        $modinfo = get_fast_modinfo($course);
        $renderer = $this->get_renderer($PAGE);
        if ($renderer && ($sections = $modinfo->get_section_info_all())) {
            foreach ($sections as $number => $section) {
                $titles[$number] = $renderer->section_title($section, $course);
            }
        }
        return array('sectiontitles' => $titles, 'action' => 'move');
    }

    /**
     * Returns the list of blocks to be automatically added for the newly created course
     *
     * @return array of default blocks, must contain two keys BLOCK_POS_LEFT and BLOCK_POS_RIGHT
     *     each of values is an array of block names (for left and right side columns)
     */
    public function get_default_blocks() {
        return array(
            BLOCK_POS_LEFT => array(),
            BLOCK_POS_RIGHT => array('search_forums', 'news_items', 'calendar_upcoming', 'recent_activity')
        );
    }

    /**
     * Definitions of the additional options that this course format uses for course
     *
     * Collapsed Topics format uses the following options (until extras are migrated):
     * - coursedisplay
     * - numsections
     * - hiddensections
     *
     * @param bool $foreditform
     * @return array of options
     */
    public function course_format_options($foreditform = false) {
        static $courseformatoptions = false;

        if ($courseformatoptions === false) {
            $courseconfig = get_config('moodlecourse');
            $courseformatoptions = array(
                'numsections' => array(
                    'default' => $courseconfig->numsections,
                    'type' => PARAM_INT,
                ),
                'hiddensections' => array(
                    'default' => $courseconfig->hiddensections,
                    'type' => PARAM_INT,
                ),
                'coursedisplay' => array(
                    'default' => CollapsedTopicsDefaults::defaultcoursedisplay,
                    'type' => PARAM_INT,
                ),
                'layoutelement' => array(
                    'default' => CollapsedTopicsDefaults::defaultlayoutelement,
                    'type' => PARAM_INT,
                ),
                'layoutstructure' => array(
                    'default' => CollapsedTopicsDefaults::defaultlayoutstructure,
                    'type' => PARAM_INT,
                ),
                'layoutcolumns' => array(
                    'default' => CollapsedTopicsDefaults::defaultlayoutcolumns,
                    'type' => PARAM_INT,
                ),
                'layoutcolumnorientation' => array(
                    'default' => CollapsedTopicsDefaults::defaultlayoutcolumnorientation,
                    'type' => PARAM_INT,
                ),
                'togglealignment' => array(
                    'default' => CollapsedTopicsDefaults::defaulttogglealignment,
                    'type' => PARAM_INT,
                ),
                'toggleiconset' => array(
                    'default' => CollapsedTopicsDefaults::defaulttoggleiconset,
                    'type' => PARAM_ALPHA,
                ),
                'toggleforegroundcolour' => array(
                    'default' => CollapsedTopicsDefaults::defaulttgfgcolour,
                    'type' => PARAM_ALPHANUM,
                ),
                'togglebackgroundcolour' => array(
                    'default' => CollapsedTopicsDefaults::defaulttgbgcolour,
                    'type' => PARAM_ALPHANUM,
                ),
                'togglebackgroundhovercolour' => array(
                    'default' => CollapsedTopicsDefaults::defaulttgbghvrcolour,
                    'type' => PARAM_ALPHANUM,
                ),
            );
        }
        if ($foreditform && !isset($courseformatoptions['coursedisplay']['label'])) {
            $courseconfig = get_config('moodlecourse');
            $sectionmenu = array();
            for ($i = 0; $i <= $courseconfig->maxsections; $i++) {
                $sectionmenu[$i] = "$i";
            }
            $courseformatoptionsedit = array(
                'numsections' => array(
                    'label' => new lang_string('numbersections', 'format_topcoll'),
                    'element_type' => 'select',
                    'element_attributes' => array($sectionmenu),
                ),
                'hiddensections' => array(
                    'label' => new lang_string('hiddensections'),
                    'help' => 'hiddensections',
                    'help_component' => 'moodle',
                    'element_type' => 'select',
                    'element_attributes' => array(
                        array(0 => new lang_string('hiddensectionscollapsed'),
                              1 => new lang_string('hiddensectionsinvisible')
                        )
                    ),
                ),
                'coursedisplay' => array(
                    'label' => new lang_string('coursedisplay'),
                    'element_type' => 'select',
                    'element_attributes' => array(
                        array(
                            COURSE_DISPLAY_SINGLEPAGE => new lang_string('coursedisplay_single'),
                            COURSE_DISPLAY_MULTIPAGE => new lang_string('coursedisplay_multi')
                        )
                    ),
                    'help' => 'coursedisplay',
                    'help_component' => 'moodle',
                ),
                'layoutelement' => array(
                    'label' => new lang_string('setlayoutelements', 'format_topcoll'),
                    'help' => 'setlayoutelements',
                    'help_component' => 'format_topcoll',
                    'element_type' => 'select',
                    'element_attributes' => array(
                        array(1 => new lang_string('setlayout_default', 'format_topcoll'),                                    // Default.
                              2 => new lang_string('setlayout_no_toggle_section_x', 'format_topcoll'),                        // No 'Topic x' / 'Week x'.
                              3 => new lang_string('setlayout_no_section_no', 'format_topcoll'),                              // No section number.
                              4 => new lang_string('setlayout_no_toggle_section_x_section_no', 'format_topcoll'),             // No 'Topic x' / 'Week x' and no section number.
                              5 => new lang_string('setlayout_no_toggle_word', 'format_topcoll'),                             // No 'Toggle' word.
                              6 => new lang_string('setlayout_no_toggle_word_toggle_section_x', 'format_topcoll'),            // No 'Toggle' word and no 'Topic x' / 'Week x'.
                              7 => new lang_string('setlayout_no_toggle_word_toggle_section_x_section_no', 'format_topcoll')) // No 'Toggle' word, no 'Topic x' / 'Week x'  and no section number.
                    )
                ),
                'layoutstructure' => array(
                    'label' => new lang_string('setlayoutstructure', 'format_topcoll'),
                    'help' => 'setlayoutstructure',
                    'help_component' => 'format_topcoll',
                    'element_type' => 'select',
                    'element_attributes' => array(
                        array(1 => get_string('setlayoutstructuretopic', 'format_topcoll'),             // Topic
                              2 => get_string('setlayoutstructureweek', 'format_topcoll'),              // Week
                              3 => get_string('setlayoutstructurelatweekfirst', 'format_topcoll'),      // Latest Week First
                              4 => get_string('setlayoutstructurecurrenttopicfirst', 'format_topcoll'), // Current Topic First
                              5 => get_string('setlayoutstructureday', 'format_topcoll'))               // Day
                    )
                ),
                'layoutcolumns' => array(
                    'label' => new lang_string('setlayoutcolumns', 'format_topcoll'),
                    'help' => 'setlayoutcolumns',
                    'help_component' => 'format_topcoll',
                    'element_type' => 'select',
                    'element_attributes' => array(
                        array(1 => get_string('one', 'format_topcoll'),   // Default
                              2 => get_string('two', 'format_topcoll'),   // Two
                              3 => get_string('three', 'format_topcoll'), // Three
                              4 => get_string('four', 'format_topcoll'))  // Four
                    )
                ),
                'layoutcolumnorientation' => array(
                    'label' => new lang_string('setlayoutcolumnorientation', 'format_topcoll'),
                    'help' => 'setlayoutcolumnorientation',
                    'help_component' => 'format_topcoll',
                    'element_type' => 'select',
                    'element_attributes' => array(
                        array(1 => get_string('columnvertical', 'format_topcoll'),
                              2 => get_string('columnhorizontal', 'format_topcoll')) // Default
                    )
                ),
                'togglealignment' => array(
                    'label' => new lang_string('settogglealignment', 'format_topcoll'),
                    'help' => 'settogglealignment',
                    'help_component' => 'format_topcoll',
                    'element_type' => 'select',
                    'element_attributes' => array(
                        array(1 => get_string('left', 'format_topcoll'),   // Left.
                              2 => get_string('center', 'format_topcoll'), // Centre.
                              3 => get_string('right', 'format_topcoll'))  // Right.
                    )
                ),
                'toggleiconset' => array(
                    'label' => new lang_string('settoggleiconset', 'format_topcoll'),
                    'help' => 'settoggleiconset',
                    'help_component' => 'format_topcoll',
                    'element_type' => 'select',
                    'element_attributes' => array(
                        array('arrow' => get_string('arrow', 'format_topcoll'), // Arrow icon set.
                              'point' => get_string('point', 'format_topcoll'), // Point icon set.
                              'power' => get_string('power', 'format_topcoll')) // Power icon set.
                    )
                ),
                'toggleforegroundcolour' => array(
                    'label' => new lang_string('settoggleforegroundcolour', 'format_topcoll'),
                    'help' => 'settoggleforegroundcolour',
                    'help_component' => 'format_topcoll',
                    'element_type' => 'tccolourpopup',
                    'element_attributes' => array(
                        array('tabindex' => -1, 'value' => CollapsedTopicsDefaults::defaulttgfgcolour)
                    )
                ),
                'togglebackgroundcolour' => array(
                    'label' => new lang_string('settogglebackgroundcolour', 'format_topcoll'),
                    'help' => 'settogglebackgroundcolour',
                    'help_component' => 'format_topcoll',
                    'element_type' => 'tccolourpopup',
                    'element_attributes' => array(
                        array('tabindex' => -1, 'value' => CollapsedTopicsDefaults::defaulttgbgcolour)
                    )
                ),
                'togglebackgroundhovercolour' => array(
                    'label' => new lang_string('settogglebackgroundhovercolour', 'format_topcoll'),
                    'help' => 'settogglebackgroundhovercolour',
                    'help_component' => 'format_topcoll',
                    'element_type' => 'tccolourpopup',
                    'element_attributes' => array(
                        array('tabindex' => -1, 'value' => CollapsedTopicsDefaults::defaulttgbghvrcolour)
                    )
                )
            );
            $courseformatoptions = array_merge_recursive($courseformatoptions, $courseformatoptionsedit);
        }
        return $courseformatoptions;
    }

    /**
     * Adds format options elements to the course/section edit form
     *
     * This function is called from {@link course_edit_form::definition_after_data()}
     *
     * @param MoodleQuickForm $mform form the elements are added to
     * @param bool $forsection 'true' if this is a section edit form, 'false' if this is course edit form
     * @return array array of references to the added form elements
     */
    public function create_edit_form_elements(&$mform, $forsection = false) {
        global $CFG;
        MoodleQuickForm::registerElementType('tccolourpopup', "$CFG->dirroot/course/format/topcoll/js/tc_colourpopup.php", 'MoodleQuickForm_tccolourpopup');

        $elements = parent::create_edit_form_elements($mform, $forsection);

        if ($forsection == false) {
            global $CFG, $USER;

            $elements[] = $mform->addElement('header', 'ctreset', get_string('ctreset', 'format_topcoll'));
            $mform->addHelpButton('ctreset', 'ctreset', 'format_topcoll', '', true);
            $elements[] = $mform->addElement('checkbox', 'resetlayout', get_string('resetlayout', 'format_topcoll'), false);
            $mform->setAdvanced('resetlayout');
            $mform->addHelpButton('resetlayout', 'resetlayout', 'format_topcoll', '', true);

            $elements[] = $mform->addElement('checkbox', 'resetcolour', get_string('resetcolour', 'format_topcoll'), false);
            $mform->addHelpButton('resetcolour', 'resetcolour', 'format_topcoll', '', true);
            $mform->setAdvanced('resetcolour');

            $elements[] = $mform->addElement('checkbox', 'resettogglealignment', get_string('resettogglealignment', 'format_topcoll'), false);
            $mform->addHelpButton('resettogglealignment', 'resettogglealignment', 'format_topcoll', '', true);
            $mform->setAdvanced('resettogglealignment');

            $elements[] = $mform->addElement('checkbox', 'resettoggleiconset', get_string('resettoggleiconset', 'format_topcoll'), false);
            $mform->addHelpButton('resettoggleiconset', 'resettoggleiconset', 'format_topcoll', '', true);
            $mform->setAdvanced('resettoggleiconset');

            if (is_siteadmin($USER)) {
                $elements[] = $mform->addElement('checkbox', 'resetalllayout', get_string('resetalllayout', 'format_topcoll'), false);
                $mform->addHelpButton('resetalllayout', 'resetalllayout', 'format_topcoll', '', true);
                $mform->setAdvanced('resetalllayout');

                $elements[] = $mform->addElement('checkbox', 'resetallcolour', get_string('resetallcolour', 'format_topcoll'), false);
                $mform->addHelpButton('resetallcolour', 'resetallcolour', 'format_topcoll', '', true);
                $mform->setAdvanced('resetallcolour');

                $elements[] = $mform->addElement('checkbox', 'resetalltogglealignment', get_string('resetalltogglealignment', 'format_topcoll'), false);
                $mform->addHelpButton('resetalltogglealignment', 'resetalltogglealignment', 'format_topcoll', '', true);
                $mform->setAdvanced('resetalltogglealignment');

                $elements[] = $mform->addElement('checkbox', 'resetalltoggleiconset', get_string('resetalltoggleiconset', 'format_topcoll'), false);
                $mform->addHelpButton('resetalltoggleiconset', 'resetalltoggleiconset', 'format_topcoll', '', true);
                $mform->setAdvanced('resetalltoggleiconset');
            }
        }

        return $elements;
    }

    /**
     * Updates format options for a course
     *
     * In case if course format was changed to 'Collapsed Topics', we try to copy options
     * 'coursedisplay', 'numsections' and 'hiddensections' from the previous format.
     * If previous course format did not have 'numsections' option, we populate it with the
     * current number of sections.  The layout and colour defaults will come from 'course_format_options'.
     *
     * @param stdClass|array $data return value from {@link moodleform::get_data()} or array with data
     * @param stdClass $oldcourse if this function is called from {@link update_course()}
     *     this object contains information about the course before update
     * @return bool whether there were any changes to the options values
     */
    public function update_course_format_options($data, $oldcourse = null) {

        // Notes: Using 'unset' to really ensure that the reset form elements never get into the database.
        //        This has to be done here so that the reset occurs after we have done updates such that the
        //        reset itself is not seen as an update.
        $resetlayout = false;
        $resetcolour = false;
        $resettogglealignment = false;
        $resettoggleiconset = false;
        $resetalllayout = false;
        $resetallcolour = false;
        $resetalltogglealignment = false;
        $resetalltoggleiconset = false;
        if (isset($data->resetlayout) == true) {
            $resetlayout = true;
            unset($data->resetlayout);
        }
        if (isset($data->resetcolour) == true) {
            $resetcolour = true;
            unset($data->resetcolour);
        }
        if (isset($data->resetalllayout) == true) {
            $resetalllayout = true;
            unset($data->resetalllayout);
        }
        if (isset($data->resettogglealignment) == true) {
            $resettogglealignment = true;
            unset($data->resettogglealignment);
        }
        if (isset($data->resettoggleiconset) == true) {
            $resettoggleiconset = true;
            unset($data->resettoggleiconset);
        }
        if (isset($data->resetallcolour) == true) {
            $resetallcolour = true;
            unset($data->resetalllayout);
        }
        if (isset($data->resetalltogglealignment) == true) {
            $resetalltogglealignment = true;
            unset($data->resetalltogglealignment);
        }
        if (isset($data->resetalltoggleiconset) == true) {
            $resetalltoggleiconset = true;
            unset($data->resetalltoggleiconset);
        }

        if ($oldcourse !== null) {
            $data = (array) $data;
            $oldcourse = (array) $oldcourse;
            $options = $this->course_format_options();
            foreach ($options as $key => $unused) {
                if (!array_key_exists($key, $data)) {
                    if (array_key_exists($key, $oldcourse)) {
                        $data[$key] = $oldcourse[$key];
                    } else if ($key === 'numsections') {
                        // If previous format does not have the field 'numsections'
                        // and $data['numsections'] is not set,
                        // we fill it with the maximum section number from the DB
                        $maxsection = $DB->get_field_sql('SELECT max(section) from {course_sections}
                            WHERE course = ?', array($this->courseid));
                        if ($maxsection) {
                            // If there are no sections, or just default 0-section, 'numsections' will be set to default
                            $data['numsections'] = $maxsection;
                        }
                    }
                }
            }
        }
        $changes = $this->update_format_options($data);

        // Now we can do the reset.
        if (($resetalllayout) || ($resetallcolour) || ($resetalltogglealignment) || ($resetalltoggleiconset)) {
            $this->reset_topcoll_setting(0, $resetalllayout, $resetallcolour, $resetalltogglealignment, $resetalltoggleiconset);
            $changes = true;
        } else if (($resetlayout) || ($resetcolour) || ($resettogglealignment) || ($resettoggleiconset)) {
            $this->reset_topcoll_setting($this->courseid, $resetlayout, $resetcolour, $resettogglealignment, $resettoggleiconset);
            $changes = true;
        }

        return $changes;
    }

    /**
     * Is the section passed in the current section?
     *
     * @param stdClass $section The course_section entry from the DB
     * @return bool true if the section is current
     */
    public function is_section_current($section) {
        $tcsettings = $this->get_settings();
        if (($tcsettings['layoutstructure'] == 2) || ($tcsettings['layoutstructure'] == 3)) {
            if ($section->section < 1) {
                return false;
            }

            $timenow = time();
            $dates = $this->format_topcoll_get_section_dates($section, $this->get_course());

            return (($timenow >= $dates->start) && ($timenow < $dates->end));
        } else if ($tcsettings['layoutstructure'] == 5) {
            if ($section->section < 1) {
                return false;
            }

            $timenow = time();
            $day = $this->format_topcoll_get_section_day($section, $this->get_course());
            $onedayseconds = 86400;
            return (($timenow >= $day) && ($timenow < ($day + $onedayseconds)));
        } else {
            return parent::is_section_current($section);
        }
    }

    /**
     * Return the start and end date of the passed section.
     *
     * @param stdClass $section The course_section entry from the DB.
     * @param stdClass $course The course entry from DB.
     * @return stdClass property start for startdate, property end for enddate.
     */
    private function format_topcoll_get_section_dates($section, $course) {
        $oneweekseconds = 604800;
        // Hack alert. We add 2 hours to avoid possible DST problems. (e.g. we go into daylight
        // savings and the date changes.
        $startdate = $course->startdate + 7200;

        $dates = new stdClass();
        $dates->start = $startdate + ($oneweekseconds * ($section->section - 1));
        $dates->end = $dates->start + $oneweekseconds;

        return $dates;
    }

    /**
     * Return the date of the passed section.
     *
     * @param stdClass $section The course_section entry from the DB.
     * @param stdClass $course The course entry from DB.
     * @return stdClass property date.
     */
    private function format_topcoll_get_section_day($section, $course) {
        $onedayseconds = 86400;
// Hack alert. We add 2 hours to avoid possible DST problems. (e.g. we go into daylight
// savings and the date changes.
        $startdate = $course->startdate + 7200;

        $day = $startdate + ($onedayseconds * ($section->section - 1));

        return $day;
    }

    /**
     * Resets the format setting to the default.
     * @param int $courseid If not 0, then a specific course to reset.
     * @param int $layout If true, reset the layout to the default in tcconfig.php.
     * @param int $colour If true, reset the colour to the default in tcconfig.php.
     * @param int $togglealignment If true, reset the toggle alignment to the default in tcconfig.php.
     * @param int $toggleiconset If true, reset the toggle icon set to the default in tcconfig.php.
     */
    public function reset_topcoll_setting($courseid, $layout, $colour, $togglealignment, $toggleiconset) {
        global $DB;

        $currentcourseid = 0;
        if ($courseid == 0) {
            $records = $DB->get_records('course_format_options', array('format' => $this->format), '', 'id,courseid');
        } else {
            $records = $DB->get_records('course_format_options', array('courseid' => $courseid, 'format' => $this->format), '', 'id,courseid');
        }

        $updatedata = array();
        if ($layout) {
            $updatedata['coursedisplay'] = CollapsedTopicsDefaults::defaultcoursedisplay;
            $updatedata['layoutelement'] = CollapsedTopicsDefaults::defaultlayoutelement;
            $updatedata['layoutstructure'] = CollapsedTopicsDefaults::defaultlayoutstructure;
            $updatedata['layoutcolumns'] = CollapsedTopicsDefaults::defaultlayoutcolumns;
            $updatedata['layoutcolumnorientation'] = CollapsedTopicsDefaults::defaultlayoutcolumnorientation;
        }
        if ($togglealignment) {
            $updatedata['togglealignment'] = CollapsedTopicsDefaults::defaulttogglealignment;
        }
        if ($colour) {
            $updatedata['toggleforegroundcolour'] = CollapsedTopicsDefaults::defaulttgfgcolour;
            $updatedata['togglebackgroundcolour'] = CollapsedTopicsDefaults::defaulttgbgcolour;
            $updatedata['togglebackgroundhovercolour'] = CollapsedTopicsDefaults::defaulttgbghvrcolour;
        }
        if ($toggleiconset) {
            $updatedata['toggleiconset'] = CollapsedTopicsDefaults::defaulttoggleiconset;
        }

        foreach ($records as $record) {
            if ($currentcourseid != $record->courseid) {
                $currentcourseid = $record->courseid; // Only do once per course.
                if (($layout) || ($togglealignment) || ($colour) || ($toggleiconset)) {
                    $ourcourseid = $this->courseid;
                    $this->courseid = $currentcourseid;
                    $this->update_format_options($updatedata);
                    $this->courseid = $ourcourseid;
                }
            }
        }
    }

    /**
     * Restores the course settings when restoring a Moodle 2.3 or below (bar 1.9) course and sets the settings when upgrading
     * from a prevous version.  Hence no need for 'coursedisplay' as that is a core rather than CT specific setting and not
     * in the old 'format_topcoll_settings' table.
     * @param int $courseid If not 0, then a specific course to reset.
     * @param int $layoutelement The layout element to use, see tcconfig.php.
     * @param int $layoutstructure The layout structure to use, see tcconfig.php.
     * @param int $layoutcolumns The layout columns to use, see tcconfig.php.
     * @param int $tgfgcolour The foreground colour to use, see tcconfig.php.
     * @param int $tgbgcolour The background colour to use, see tcconfig.php.
     * @param int $tgbghvrcolour The background hover colour to use, see tcconfig.php.
     */
    public function restore_topcoll_setting($courseid, $layoutelement, $layoutstructure, $layoutcolumns, $tgfgcolour, $tgbgcolour, $tgbghvrcolour) {
        $currentcourseid = $this->courseid;  // Save for later - stack data model.
        $this->courseid = $courseid;
        // Create data array.
        $data = array(
            'layoutelement' => $layoutelement,
            'layoutstructure' => $layoutstructure,
            'layoutcolumns' => $layoutcolumns,
            'toggleforegroundcolour' => $tgfgcolour,
            'togglebackgroundcolour' => $tgbgcolour,
            'togglebackgroundhovercolour' => $tgbghvrcolour);

        $this->update_course_format_options($data);

        $this->courseid = $currentcourseid;
    }

    /**
     * Updates the number of columns when the renderer detects that they are wrong.
     * @param int $layoutcolumns The layout columns to use, see tcconfig.php.
     */
    public function update_topcoll_columns_setting($layoutcolumns) {
        // Create data array.
        $data = array('layoutcolumns' => $layoutcolumns);

        $this->update_course_format_options($data);
    }

}

/**
 * Used to display the course structure for a course where format=Collapsed Topics
 *
 * This is called automatically by {@link load_course()} if the current course
 * format = Collapsed Topics.
 *
 * @param navigation_node $navigation The course node.
 * @param array $path An array of keys to the course node.
 * @param stdClass $course The course we are loading the section for.
 */
function callback_topcoll_load_content(&$navigation, $course, $coursenode) {
    return $navigation->load_generic_course_sections($course, $coursenode, 'topcoll');
}

/**
 * The string that is used to describe a section of the course.
 *
 * @return string The section description.
 */
function callback_topcoll_definition() {
    return get_string('sectionname', 'format_topcoll');
}

/**
 * Deletes the user preference entries for the given course upon course deletion.
 * CONTRIB-3520.
 */
function format_topcoll_delete_course($courseid) {
    global $DB;
    $DB->delete_records("user_preferences", array("name" => 'topcoll_toggle_' . $courseid));
}