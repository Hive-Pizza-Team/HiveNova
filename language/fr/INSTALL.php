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

$LNG['back'] = 'Retour';
$LNG['continueUpgrade'] = 'Mise à jour !';
$LNG['login'] = 'Connexion';
$LNG['menu_upgrade'] = 'Mise à jour';
$LNG['intro_install'] = 'Vers l\'installation';
$LNG['intro_upgrade_head'] = '2Moons déjà installé ?';
$LNG['intro_upgrade_text'] = '<p>Vous avez déjà installé 2Moons et voulez une mise à jour facile ?</p><p>Ici, vous pouvez mettre à jour votre ancienne base de données en quelques clics !</p>';
$LNG['upgrade_success'] = 'Mise à jour de la base de données réussie. La base de données est maintenant à la révision %s.';
$LNG['upgrade_nothingtodo'] = 'Aucune action requise. La base de données est déjà à la révision %s.';
$LNG['upgrade_back'] = 'Retour';
$LNG['upgrade_intro_welcome'] = 'Bienvenue dans le programme de mise à jour de la base de données !';
$LNG['upgrade_available'] = 'Des mises à jour sont disponibles pour votre base de données ! La base de données est à la révision %s et peut être mise à jour jusqu\'à la révision %s.<br><br>Veuillez choisir dans le menu suivant la première mise à jour SQL à installer :';
$LNG['upgrade_notavailable'] = 'La révision utilisée %s est la dernière pour votre base de données.';
$LNG['upgrade_required_rev'] = 'Le programme de mise à jour ne peut fonctionner qu\'à partir de la révision r2579 (2Moons v1.7) ou ultérieure.';
$LNG['licence_head'] = 'Conditions de licence';
$LNG['licence_desc'] = 'Veuillez lire les conditions de licence ci-dessous. Utilisez la barre de défilement pour voir tout le contenu du document.';
$LNG['licence_accept'] = 'Pour continuer l\'installation de 2Moons, vous devez accepter les termes et conditions de la licence de 2Moons.';
$LNG['licence_need_accept'] = 'Si vous souhaitez continuer l\'installation, vous devez accepter les termes de la licence.';
$LNG['req_head'] = 'Configuration système requise';
$LNG['req_desc'] = 'Avant de procéder à l\'installation, 2Moons effectuera quelques tests pour vérifier que votre serveur supporte 2Moons. Il est suggéré de lire attentivement les résultats et de ne pas procéder tant que tout n\'est pas vérifié.';
$LNG['req_php_need_desc'] = '<strong>Requis</strong> — PHP est le langage de base de 2Moons. La version PHP 5.2.5 ou supérieure est requise pour que tous les modules fonctionnent correctement.';
$LNG['reg_gd_desc'] = '<strong>Optionnel</strong> — La bibliothèque de traitement graphique &raquo;gdlib&laquo; est responsable de la génération d\'images dynamiques. Certaines fonctionnalités ne fonctionneront pas sans elle.';
$LNG['reg_global_desc'] = '2Moons fonctionnera également si cette configuration est installée sur votre serveur. Cependant, il est recommandé pour des raisons de sécurité de désactiver "register_globals" dans l\'installation PHP, si possible.';
$LNG['req_ftp_head'] = 'Saisir les informations FTP';
$LNG['req_ftp_desc'] = 'Entrez vos informations FTP pour que 2Moons corrige automatiquement les problèmes. Vous pouvez également attribuer manuellement les permissions d\'écriture.';
$LNG['req_ftp_error_data'] = 'Les informations fournies ne permettent pas la connexion au serveur FTP, ce lien a échoué.';
$LNG['req_ftp_error_dir'] = 'Le répertoire que vous avez saisi est invalide ou inexistant.';
$LNG['reg_pdo_active'] = 'Support de l\'extension &raquo;PDO&laquo;';
$LNG['reg_pdo_desc'] = '<strong>Prérequis</strong> — Vous devez fournir le support PDO en PHP.';
$LNG['step1_head'] = 'Configurer la base de données d\'installation';
$LNG['step1_desc'] = 'Maintenant qu\'il a été déterminé que 2Moons peut être installé sur votre serveur, vous devez fournir quelques informations. Si vous ne savez pas comment exécuter une connexion à une base de données, contactez d\'abord votre hébergeur ou le forum 2Moons pour de l\'aide.';
$LNG['step2_db_no_dbname'] = 'Vous n\'avez pas spécifié le nom de la base de données.';
$LNG['step2_db_too_long'] = 'Le préfixe de table est trop long. Il doit contenir au maximum 36 caractères.';
$LNG['step2_config_exists'] = 'config.php existe déjà !';
$LNG['step2_db_done'] = 'La connexion à la base de données a réussi !';
$LNG['step3_head'] = 'Créer les tables de la base de données';
$LNG['step3_desc'] = 'Les tables nécessaires pour la base de données 2Moons ont été créées et remplies avec des valeurs par défaut. Pour passer à l\'étape suivante, terminez l\'installation de 2Moons.';
$LNG['step3_db_error'] = 'Échec de la création des tables de la base de données :';
$LNG['step4_head'] = 'Compte administrateur';
$LNG['step4_desc'] = 'L\'assistant d\'installation va maintenant créer un compte administrateur pour vous. Entrez le nom d\'utilisateur, votre mot de passe et votre e-mail.';
$LNG['step4_admin_name'] = 'Nom d\'utilisateur de l\'administrateur :';
$LNG['step4_admin_name_desc'] = 'Saisissez le nom d\'utilisateur avec une longueur de 3 à 20 caractères.';
$LNG['step4_admin_pass'] = 'Mot de passe de l\'administrateur :';
$LNG['step4_admin_pass_desc'] = 'Saisissez un mot de passe d\'une longueur de 6 à 30 caractères.';
$LNG['step4_admin_mail'] = 'E-mail de contact :';
$LNG['step6_head'] = 'Installation terminée !';
$LNG['step6_desc'] = 'Vous avez installé avec succès le système 2Moons.';
$LNG['step6_info_head'] = 'Commencer à utiliser 2Moons maintenant !';
$LNG['step6_info_additional'] = 'En cliquant sur le bouton ci-dessous, vous serez redirigé vers la page d\'administration. Il sera avantageux d\'explorer les outils d\'administration de 2Moons.<br/><br/><strong>Veuillez supprimer le fichier &raquo;includes/ENABLE_INSTALL_TOOL&laquo; ou modifier son nom. La présence de ce fichier peut mettre votre jeu en danger en permettant à quelqu\'un de réécrire l\'installation !</strong>';
$LNG['step8_need_fields'] = 'Vous devez remplir tous les champs.';
