<?php

/**
 *  2Moons
 *  Copyright (C) 2011  Slaver
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package 2Moons
 * @author Slaver <slaver7@gmail.com>
 * @copyright 2009 Lucky <lucky@xgproyect.net> (XGProyecto)
 * @copyright 2011 Slaver <slaver7@gmail.com> (Fork/2Moons)
 * @license http://www.gnu.org/licenses/gpl.html GNU GPLv3 License
 * @version 1.5 (2011-07-31)
 * @info $Id: CUSTOM.php 2095 2011-12-21 19:39:40Z slaver7 $
 * @link http://code.google.com/p/2moons/
 */
 
// If you have custom lang vars, you can include them here. The file ll be not overwrite by updatemanager
// Also you can overwrite exists var, too. You use the same key.

$LNG['fb_button'] = 'Feedback';
$LNG['fb_title'] = 'Send feedback';
$LNG['fb_intro'] = 'Describe the issue or idea. Context below is attached automatically.';
$LNG['fb_type'] = 'Type (optional)';
$LNG['fb_type_bug'] = 'Bug';
$LNG['fb_type_idea'] = 'Idea';
$LNG['fb_type_other'] = 'Other';
$LNG['fb_description'] = 'Description';
$LNG['fb_description_placeholder'] = 'What happened? What did you expect?';
$LNG['fb_required'] = 'required';
$LNG['fb_capture_screenshot'] = 'Include a screenshot of this page';
$LNG['fb_attach_screenshot'] = 'Or attach an image (optional, max 2 MB)';
$LNG['fb_context_heading'] = 'Auto-captured context';
$LNG['fb_send'] = 'Send';
$LNG['fb_cancel'] = 'Cancel';
$LNG['fb_close'] = 'Close';
$LNG['fb_success'] = 'Thanks! Your report was sent. Reference: %s';
$LNG['fb_error_empty'] = 'Please enter a description.';
$LNG['fb_error_too_long'] = 'Description is too long.';
$LNG['fb_error_screenshot_size'] = 'Screenshot must be 2 MB or smaller.';
$LNG['fb_error_screenshot_type'] = 'Screenshots must be PNG, JPEG, GIF, or WebP.';
$LNG['fb_error_screenshot_upload'] = 'Could not upload the screenshot. Try again without it.';
$LNG['fb_error_csrf'] = 'Security token expired. Reload the page and try again.';
$LNG['fb_error_rate'] = 'Please wait %d seconds before sending more feedback.';
$LNG['fb_error_retry'] = 'Could not send feedback. Please try again.';
$LNG['fb_success_no_screenshot'] = 'Thanks! Your report was sent without a screenshot. Reference: %s';
