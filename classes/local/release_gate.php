<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Release gating for the Authorship Check activity.
 *
 * @package    mod_authorcheck
 * @copyright  2026 Pius Kwao Gadosey <kwaoproj@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_authorcheck\local;

/**
 * Release gating for an authorcheck activity.
 */
class release_gate {
    /**
     * Are questions released for this activity instance?
     *
     * Released if the lecturer has manually released them, OR a release time
     * is set and has passed.
     *
     * @param \stdClass $instance Row from the authorcheck table.
     * @return bool
     */
    public static function is_released(\stdClass $instance): bool {
        if (!empty($instance->manualreleased)) {
            return true;
        }
        if (!empty($instance->releasetime) && time() >= (int) $instance->releasetime) {
            return true;
        }
        return false;
    }

    /**
     * A human-readable status line for the current release state.
     *
     * @param \stdClass $instance
     * @return string
     */
    public static function status_label(\stdClass $instance): string {
        if (self::is_released($instance)) {
            return get_string('statusreleased', 'mod_authorcheck');
        }
        if (!empty($instance->releasetime)) {
            return get_string('statusscheduled', 'mod_authorcheck', userdate((int) $instance->releasetime));
        }
        return get_string('statuslocked', 'mod_authorcheck');
    }
}
