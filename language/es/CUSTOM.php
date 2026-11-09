<?php

/**
 *  2Moons 
 *   by Jan-Otto Kröpke 2009-2016
 *
 * For the full copyright and license information, please view the LICENSE
 *
 * @package 2Moons
 * @author Jan-Otto Kröpke <slaver7@gmail.com>
 * @copyright 2009 Lucky
 * @copyright 2016 Jan-Otto Kröpke <slaver7@gmail.com>
 * @licence MIT
 * @version 1.8.0
 * @link https://github.com/jkroepke/2Moons
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
