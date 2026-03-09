<?php
// Traduction Française by BigTwoProduction (KickXAss4Ever & Apocalypto2202) - All rights reserved (C) 2016
// Web : http://www.big-two.tk
// Version 1.0 - Initial release
// Version 1.1 - Decode accent HTML to UTF-8 format & small spellchecking

$LNG['continue']			= "Continue";

$LNG['menu_intro']			= "Intro";
$LNG['menu_install']		= "Install";
$LNG['menu_license']		= "License";

$LNG['title_install']		= 'Installeur';

$LNG['intro_lang']			= "Language";
$LNG['intro_instal']		= "Installation";
$LNG['intro_welcome']		= "Bievenue dans 2Moons!";
$LNG['intro_text']			= "Un des meilleurs clones Ogame : 2 Moons.<br>The latest is and 2 Moons the stablest flat rate was ever developed. 2 of Moon shines by stability, flexibility, dynamics, quality and user-friendliness. We hope always to be better than her expectations.<br><br>The installation system guides you through the install or upgrade from an older version to the latest. On questions or trouble don't hesitate to contact us.<br><br>2Moons is an open source project licensed under the GNU GPL v3. For the license please click on the item in the menu.<br><br>Before the installation starts, a test is conducted, if system requirements are met.";

$LNG['reg_yes']				= "Oui";
$LNG['reg_no']				= "Non";
$LNG['reg_found']			= "Trouvé";
$LNG['reg_not_found']		= "Non trouvé";
$LNG['reg_writable']		= "Inscriptibles";
$LNG['reg_not_writable']	= "Non inscriptibles";
$LNG['reg_file']			= "Fichier";
$LNG['reg_dir']				= "Dossier";
$LNG['reg_gd_need']			= "GB-Lib disponible ?";
$LNG['reg_mysqli_active']	= "MySQLi disponible ?";
$LNG['reg_bcmath_need']		= "BCMath disponible ?";
$LNG['reg_iniset_need']		= "ini_set() disponible ?";
$LNG['reg_global_need']		= "register_globals desactivé ?";
$LNG['reg_json_need']		= "JSON disponible ?";
$LNG['req_php_need']		= "PHP-Version (min. 5.2.5)";
$LNG['req_ftp']				= "FTP";
$LNG['req_ftp_info']		= "Entrer vos accès FTP.";
$LNG['req_ftp_host']		= "FTP Host";
$LNG['req_ftp_username']	= "Username";
$LNG['req_ftp_password']	= "Password";
$LNG['req_ftp_dir']			= "Dossier de 2Moons";
$LNG['req_ftp_send']		= "Envoyer";
$LNG['req_ftp_pass_info']	= "Entrer une informations sur le mot de pass.";

$LNG['step1_mysql_server']	= "MySQL-DB-Server: <br>Standard: localhost";
$LNG['step1_mysql_port']	= "MySQL-DB-Server-Port: <br>Standard: 3306";
$LNG['step1_mysql_dbname']	= "MySQL-DB-Name: <br> Ex.: Game";
$LNG['step1_mysql_dbuser']	= "MySQL-DB-User: <br> Ex.: root";
$LNG['step1_mysql_dbpass']	= "MySQL-DB-Password: <br> Ex.: 12345";
$LNG['step1_mysql_prefix']	= "MySQL-DB-Prefix: <br> Ex.: uni1_";

$LNG['step2_db_error']		= "Impossible de crées la base de données: %s";
$LNG['step2_db_con_fail']	= "Aucune connexion avec la base de données.<br> %s";
$LNG['step2_conf_op_fail']	= "config.php n'est pas en CHMOD 777!";
$LNG['step2_db_connet_ok']	= "Connexion au tables réussi...";
$LNG['step2_db_create_ok']	= "Tables crée avec succès...";
$LNG['step2_conf_create']	= "config.php à été crée avec succès...";
$LNG['step2_prefix_invalid']	= 'Le préfixe DB doit contenir que des caractères alphanumériques et caractères de soulignement.';

$LNG['step3_create_admin']	= "Création d'un compte Administrateur";
$LNG['step3_admin_name']	= "Pseudo de l'Administrateur:";
$LNG['step3_admin_pass']	= "Mot de passe Administrateur:";
$LNG['step3_admin_mail']	= "E-Mail Administrateur:";


$LNG['step4_need_fields']	= "Vous devez remplir tous les champs!";

$LNG['sql_universe']		= 'Univers';
$LNG['sql_close_reason']	= 'Le jeu est actuellement fermé';
$LNG['sql_welcome']			= 'Bienvenue sur ';

$LNG['back'] = 'Back';
$LNG['continueUpgrade'] = 'Upgrade!';
$LNG['login'] = 'Login';
$LNG['menu_upgrade'] = 'Upgrade';
$LNG['intro_install'] = 'To installation';
$LNG['intro_upgrade_head'] = '2Moons already installed?';
$LNG['intro_upgrade_text'] = '<p>You have already installed 2Moons and want easy updating?</p><p>Here you can update your old database with just a few clicks!</p>';
$LNG['upgrade_success'] = 'Update of the database successfully. Database is now available on the revision %s.';
$LNG['upgrade_nothingtodo'] = 'No action is required. Database is already up to revision %s.';
$LNG['upgrade_back'] = 'Back';
$LNG['upgrade_intro_welcome'] = 'Welcome to the database upgrader!';
$LNG['upgrade_available'] = 'Available updates for your database! The database is at the revision %s and can update to revision %s.<br><br>Please choose from the following menu to the first SQL update to install:';
$LNG['upgrade_notavailable'] = 'The used revision %s is the latest for your database.';
$LNG['upgrade_required_rev'] = 'The Updater can work only from revision r2579 (2Moons v1. 7) or later.';
$LNG['licence_head'] = 'License terms';
$LNG['licence_desc'] = 'Please read the license terms below. Use the scroll bar to see all the contents of the document';
$LNG['licence_accept'] = 'To continue the installation of 2Moons, you need to agree to the terms and conditions of lincense of 2Moons';
$LNG['licence_need_accept'] = 'If you want to continue with the installation, will s that accept the terms of license';
$LNG['req_head'] = 'System requirements';
$LNG['req_desc'] = 'Before the installation proceed, 2Moons will be some tests to verify that your server supports the 2Moons, so ensure that the 2Moons can be installed. Its suggested that you read carefully the results, and do not proceed until all these be checked.';
$LNG['req_php_need_desc'] = '<strong>Required</strong> — PHP is the language code base of 2Moons. This is the required PHP version 5.2.5 or higher so that all modules work correctly';
$LNG['reg_gd_desc'] = '<strong>Optional</strong> — Graphic processing library &raquo;gdlib&laquo; Is responsible for the generation of dynamic images. They work without some of the features of the software.';
$LNG['reg_global_desc'] = '2Moons will also work, if this configuration is installed on your server. However, it is recommended for security reasons, disable \"register_globals\" in PHP installation, if that is possible.';
$LNG['req_ftp_head'] = 'Insert information of FTP';
$LNG['req_ftp_desc'] = 'Write your information from FTP so 2Moons automatically fix problems. Alternatively, you can also manually assign permissions to write.';
$LNG['req_ftp_error_data'] = 'The information provided does not allow you to connect to the FTP server, so this link failed';
$LNG['req_ftp_error_dir'] = 'The story that directory you entered is invalid or not existing';
$LNG['reg_pdo_active'] = 'Support &raquo;PDO&laquo; Extension';
$LNG['reg_pdo_desc'] = '<strong>Prerequisite</strong> — You need to provide support for PDO in PHP.';
$LNG['step1_head'] = 'Configure the installation database';
$LNG['step1_desc'] = 'Now that it has been determined that 2Moons can be installed on your server, s should provide some information. If you dont know how to run a link database, contact your hosting provider first or with the 2Moons forum for help and support. When you insert the data, checks were introduced properly';
$LNG['step2_db_no_dbname'] = 'You dont specified the name for the database';
$LNG['step2_db_too_long'] = 'The table prefix is too long. Must contain at most 36 characters';
$LNG['step2_config_exists'] = 'config.php already exists!';
$LNG['step2_db_done'] = 'The connection to the database was successful!';
$LNG['step3_head'] = 'Create database tables';
$LNG['step3_desc'] = 'The tables needed for the 2Moons database already have been created and populated with default values. To go to the next step, conclude the installation of 2Moons';
$LNG['step3_db_error'] = 'Failed to create the database tables:';
$LNG['step4_head'] = 'Administrator account';
$LNG['step4_desc'] = 'The installation wizard will now create an administrator account for you. Writes the name of use, your password and your email';
$LNG['step4_admin_name'] = 'Use name of Administrator:';
$LNG['step4_admin_name_desc'] = 'Type the name to use with the length of 3 to 20 characters';
$LNG['step4_admin_pass'] = 'Password of Administrator:';
$LNG['step4_admin_pass_desc'] = 'Type a password with a length of 6 to 30 characters';
$LNG['step4_admin_mail'] = 'Contact E-mail:';
$LNG['step6_head'] = 'Installation completed!';
$LNG['step6_desc'] = 'You installed with success the 2Moons system';
$LNG['step6_info_head'] = 'Getting and using the 2Moons now!';
$LNG['step6_info_additional'] = 'If clicking the button below, will s are redirected to the page of administration .AI will be a good advantage to get ares to explore 2Moons administrator tools.<br/><br/><strong>Please delete the &raquo;includes/ENABLE_INSTALL_TOOL&laquo; or modify the filename. With the existence of this file, you can cause your game at risk by allowing someone rewrite the installation!</strong>';
$LNG['step8_need_fields'] = 'You must fill in all fields.';
