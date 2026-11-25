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

$LNG['fb_button'] = 'Retours';
$LNG['fb_title'] = 'Envoyer un retour';
$LNG['fb_intro'] = 'Décrivez le problème ou l’idée. Le contexte ci-dessous est joint automatiquement.';
$LNG['fb_type'] = 'Type (facultatif)';
$LNG['fb_type_bug'] = 'Bug';
$LNG['fb_type_idea'] = 'Idée';
$LNG['fb_type_other'] = 'Autre';
$LNG['fb_description'] = 'Description';
$LNG['fb_description_placeholder'] = 'Que s’est-il passé ? À quoi vous attendiez-vous ?';
$LNG['fb_required'] = 'requis';
$LNG['fb_capture_screenshot'] = 'Joindre une capture d’écran de cette page';
$LNG['fb_attach_screenshot'] = 'Ou joindre une image (facultatif, 2 Mo max)';
$LNG['fb_context_heading'] = 'Contexte capturé automatiquement';
$LNG['fb_send'] = 'Envoyer';
$LNG['fb_cancel'] = 'Annuler';
$LNG['fb_close'] = 'Fermer';
$LNG['fb_success'] = 'Merci ! Votre signalement a été envoyé. Référence : %s';
$LNG['fb_error_empty'] = 'Veuillez saisir une description.';
$LNG['fb_error_too_long'] = 'La description est trop longue.';
$LNG['fb_error_screenshot_size'] = 'La capture d’écran doit faire 2 Mo ou moins.';
$LNG['fb_error_screenshot_type'] = 'Les captures doivent être au format PNG, JPEG, GIF ou WebP.';
$LNG['fb_error_screenshot_upload'] = 'Impossible d’envoyer la capture. Réessayez sans elle.';
$LNG['fb_error_csrf'] = 'Le jeton de sécurité a expiré. Rechargez la page et réessayez.';
$LNG['fb_error_rate'] = 'Veuillez patienter %d secondes avant d’envoyer un autre retour.';
$LNG['fb_error_retry'] = 'Impossible d’envoyer le retour. Veuillez réessayer.';
$LNG['fb_success_no_screenshot'] = 'Merci ! Votre signalement a été envoyé sans capture. Référence : %s';
