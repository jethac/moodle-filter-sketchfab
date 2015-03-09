<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Filter converting Sketchfab URLs in the text to embedded Sketchfab viewers.
 *
 * @package    filter
 * @subpackage sketchfabembed
 * @copyright  2015 Jetha Chan <jetha@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined ('MOODLE_INTERNAL') || die();

class filter_sketchfabembed extends moodle_text_filter {

    const SKETCHFAB_OEMBED_ENDPOINT = 'https://sketchfab.com/oembed';
    const SKETCHFAB_HOME_URL = 'https://sketchfab.com';
    const SKETCHFAB_MODELPAGE_URL = 'https://sketchfab.com/models';
    const SKETCHFAB_API_ENDPOINT = 'https://api.sketchfab.com/v2/models';

    /**
     * @var int Width of Sketchfab embed.
     */
    private $width = 600;

    /**
     * @var int Height of Sketchfab embed.
     */
    private $height = 400;

    /**
     * Apply the filter to the text
     *
     * @see filter_manager::apply_filter_chain()
     * @param string $text to be processed by the text
     * @param array $options filter options
     * @return string text after processing
     */
    public function filter($text, array $options = array()) {

        // Early out if necessary.
        if (!is_string($text) ||
            empty($text) ||
            stripos($text, '</a>') === false ||
            stripos($text, 'sketchfab.com/models') === false
        ) {
            return $text;
        }

        $this->convert_sketchfablinks_into_embeds($text);

        return $text;
    }

    /**
     * Given some text, this function searches for a Sketchfab link of form
     *
     *
     * @param string $text Passed in by reference, the string to be searched for urls.
     */
    protected function convert_sketchfablinks_into_embeds (&$text) {

        global $CFG;


        $regex = '/(?i)<a href=\"http[s]?:\\/\\/sketchfab.com\\/models\\/(\\w+)\".*>(.+?)<\\/a>/ui';
        $rval = array();
        $success = preg_match_all($regex, $text, $rval);
        $targets = $rval[0];
        $modelids = $rval[1];
        $linktext = $rval[2];
        $embeds = array();

        if (!$success)
            return;

        // Create a new instance of the Moodle cURL class to use.
        $curl = new curl();

        // Build all embeds.
        for ($i = 0; $i < count($targets); $i++) {

            $modelid = $modelids[$i];

            // Build a Sketchfab embed.
            // - make a curl request to get metadata
            $metadata = $curl->get(self::SKETCHFAB_API_ENDPOINT . '/' . $modelid);
            if (!$metadata) {
                continue;
            }
            $metajson = json_decode($metadata, true);
            $author = $metajson['user']['displayName'];
            $modelname = $metajson['name'];

            // @todo Replace with Mustache-based approach for Moodle 2.9.
            // - iframe
            $embed = html_writer::tag(
                'iframe',
                '',
                array(
                    'width' => $this->width,
                    'height' => $this->height,
                    'src' => self::SKETCHFAB_MODELPAGE_URL . '/' . $modelid . '/embed',
                    'frameborder' => 0,
                    'allowfullscreen' => 'true',
                    'mozallowfullscreen' => 'true',
                    'webkitallowfullscreen' => 'true',
                    'onmousewheel' => ''
                )
            );
            // - meta
            $a = new stdClass();
            $a->modelname = html_writer::link(
                self::SKETCHFAB_MODELPAGE_URL . '/' . $modelid . '?utm_source=oembed&utm_medium=embed&utm_campaign=' . $modelid,
                $modelname,
                array(
                    'target' => '_blank'
                )
            );
            $a->author = html_writer::link(
                self::SKETCHFAB_HOME_URL . '/' . $author . '?utm_source=oembed&utm_medium=embed&utm_campaign=' . $modelid,
                $author,
                array(
                    'target' => '_blank'
                )
            );
            $a->sketchfab = html_writer::link(
                self::SKETCHFAB_HOME_URL . '?utm_source=oembed&utm_medium=embed&utm_campaign=' . $modelid,
                'Sketchfab',
                array(
                    'target' => '_blank'
                )
            );
            $embed .= html_writer::div(
                get_string('modeldesc', 'filter_sketchfabembed', $a),
                'sketchfab-embed-desc'
            );
            $embed = html_writer::div(
                $embed,
                'sketchfab-embed'
            );

            // Push onto the embed array.
            $embeds[] = $embed;
        }

        // Replace.
        $text = str_replace($targets, $embeds, $text);

    }

}