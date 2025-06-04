<?php
/**
 *  2Moons 
 *   by Jan-Otto Kröpke 2009-2016
 *
 * For the full copyright and license information, please view the LICENSE
 *
 * @package 2Moons
 * @author Jan-Otto Kröpke <slaver7@gmail.com>
 * @author 2025 Incublus <@incublus on Hive (https://hive.blog/@incublus)>
 * @copyright 2009 Lucky
 * @copyright 2016 Jan-Otto Kröpke <slaver7@gmail.com>
 * @licence MIT
 * @version 1.8.0
 * @link https://github.com/jkroepke/2Moons
 */

// Turkce'ye Ibrahim Senyer tarafindan cevirilmistir. Butun Haklari saklidir (C) 2013
// 2Moons - Copyright (C) 2010-2012 Slaver
// Translated into Turkish by Ibraihm Senyer . All rights reversed (C) 2013
// 2Moons - Copyright (C) 2010-2012 Slaver

// Site Title
$LNG['siteTitleIndex']				= 'Ana Sayfa';
$LNG['siteTitleRegister']			= 'Kayıt Ol';
$LNG['siteTitleScreens']			= 'Ekran Görüntüleri';
$LNG['siteTitleBanList']			= 'Banlananlar';
$LNG['siteTitleBattleHall']			= 'En Büyük Savaşlar';
$LNG['siteTitleRules']				= 'Kurallar';
$LNG['siteTitleNews']				= 'Haberler';
$LNG['siteTitleDisclamer']			= 'Iletişim';
$LNG['siteTitleLostPassword']		= 'Şifremi Unuttum?';

// Menu
$LNG['forum']						= 'Forum';
$LNG['menu_index']					= 'Anasayfa';
$LNG['menu_news']					= 'Haberler';
$LNG['menu_rules']					= 'Kurallar';
$LNG['menu_banlist']				= 'Banlananlar';
$LNG['menu_battlehall']				= 'En Büyük Savaşlar';
$LNG['menu_disclamer']				= 'Iletişim';
$LNG['menu_register']				= 'Kayıt';

// Universe select
$LNG['chose_a_uni']					= 'Evreni Seçiniz';
$LNG['universe']					= 'Evren';
$LNG['uni_closed']					= ' (Offline)';

// Button
$LNG['buttonRegister']				= 'Kayıt Ol!';
$LNG['buttonRegisterHive']			= 'Hive ile kayıt olun!';
$LNG['buttonScreenshot']			= 'Ekran Görüntüleri';
$LNG['buttonLostPassword']			= 'Şifremi Unuttum?';

// Start
$LNG['gameInformations']			= "Gerçek zamanlı uzay strateji oyunu.\nBüyük bir rekabet seni bekliyor.\nTek ihtiyacın internet.Herhangi bir tarayıcı ile oynayabilirsin. Internet Explorer, Mozilla, Chrome\nÜcretsiz Kayıt";

// Login
$LNG['loginHeader']					= 'Giriş';
$LNG['loginUsername']				= 'Kullanıdı Adı';
$LNG['loginPassword']				= 'Şifre';
$LNG['loginButton']					= 'Giriş';
$LNG['loginInfo']					= 'Giriş yaparak tüm %si kabul etmiş oluyorsunuz.';
$LNG['loginWelcome']				= ' %s hoş geldiniz ';
$LNG['loginServerDesc']				= '%s tam zamanlı bir uzay strateji oyunu. Yüzlerce oyuncunun <strong>aynı anda beraber oynayabildiği</strong> ve ellerinden geleni yaptığı bir oyun. Tek ihtiyacın olan herhangi bir web tarayıcısı.';

// Register
$LNG['registerFacebookAccount']		= 'Facebook Hesabı';
$LNG['registerUsername']			= 'Kullanıcı Adı';
$LNG['registerUsernameDesc']		= 'Kullanıcı adı 3 ila 25 karakter arasında olmalıdır ayrıca sadece rakam, harf,tire (-) ve altçizgi (_) kullanılabilir. ';
$LNG['registerPassword']			= 'Şifre';
$LNG['registerPasswordDesc']		= 'Şifre en az %s karakterden oluşmalıdır.';
$LNG['registerPasswordReplay']		= 'Tekrar şifre';
$LNG['registerPasswordReplayDesc']	= 'Lütfen şifrenizi tekrar giriniz';
$LNG['registerEmail']				= 'E-Mail';
$LNG['registerEmailDesc']			= 'Lütfen e-mail adresinizi yazınız!';
$LNG['registerEmailReplay']			= 'Tekrar email';
$LNG['registerEmailReplayDesc']		= 'Güvenlik icin e-mail adrenizi tekrar yazınız.!';
$LNG['registerLanguage']			= 'Dil';
$LNG['registerReferral']			= 'Öneren:';
$LNG['registerCaptcha']				= 'Güvenlik kodu';
$LNG['registerCaptchaDesc']			= 'Lütfen boş alana asağıdaki karakterleri giriniz. Büyük küçük harf duyarlı değildir. ';
$LNG['registerCaptchaReload']		= 'Yeni Kod Iste.';
$LNG['registerRules']				= 'Kurallar';
$LNG['registerRulesDesc']			= '%si kabul ediyorum';
$LNG['hiveAccount']                 = 'Hive Hesabı';

$LNG['registerBack']				= 'Geri';
$LNG['registerNext']				= 'Ileri';

$LNG['registerErrorUniClosed']		= 'Bu evren için kayıtlar kapandı.!';
$LNG['registerErrorUsernameEmpty']	= 'Kullanıcı adı girmediniz!';
$LNG['registerErrorUsernameChar']	= 'Kullanıcı adı sadece rakam, harf,tire (-) ve altçizgi (_) den oluşur!';
$LNG['registerErrorUsernameExist']	= 'Bu kullanıcı adı zaten mevcut!';
$LNG['registerErrorPasswordLength']	= 'Şifre en az %s karakter olmak zorunda';
$LNG['registerErrorPasswordSame']	= 'Şifreler birbirini tutmuyor!';
$LNG['registerErrorMailEmpty']		= 'Lütfen e-mail adresini giriniz!';
$LNG['registerErrorMailInvalid']	= 'E-mail adresini yanlış girdiniz!';
$LNG['registerErrorMailSame']		= 'Farklı 2 email adresi girdiniz!';
$LNG['registerErrorMailExist']		= 'Bu email zaten kayıtlı!';
$LNG['registerErrorRules']			= 'Oyuna başlamadan önce kuralları kabul etmelisiniz!';
$LNG['registerErrorCaptcha']		= 'Güvenlik kodu doğru değil!';
$LNG['registerErrorHiveAccountInvalid']	= 'Geçerli bir Hive hesabı girmelisiniz!';
$LNG['registerErrorHiveAccountExist']	= 'Hive hesabı zaten kayıtlı!';

$LNG['registerMailVertifyTitle']	= 'Activation of registration on the game: %s';
$LNG['registerMailVertifyError']	= 'Email göndermede başarısız oldu: %s';

$LNG['registerMailCompleteTitle']	= ' %s dünyasina hoş geldiniz!';

$LNG['registerSendComplete']		= 'Kayıt olduğunuz için teşekkürler. Daha fazla bilgi için mail adresinizi kontrol ediniz.';

$LNG['registerWelcomePMSenderName']	= 'Admin';
$LNG['registerWelcomePMSubject']	= 'Hoş geldiniz';
$LNG['registerWelcomePMText']		= ' %s dünyasina hoş geldin! Öncelikle solar enerji santrali yapmalısın, çünkü ham madde üretimi için enerjiye ihtiyacin var. Insa etmek için soldaki menüden binalara tıklayıp,  açılan pencerede yukarıdan 4. bina (solar enerji) binasını inşa et demen lazım </br> Enerjiden sonra madenleri inşa etmeye başlayabilirsin. </br></br> Gemi üretmek içinse tersane yapmalısın. Onun için de Robot Fabrikasını 2. kademeye getirmelisin.  Hangi binanın hangisini, ya da hangi geminin üretilmesi icin nelere ihtiyacın var görmek icin soldaki menüden teknoloji butonuna tıklayip görebilirsin. </br> Eğer soruların varsa, yeni başlayanlar menüsüne girebilirsin ya da destek bileti gönderebilirsin.  </br></br> Hoş ve güzel vakit geçirmen dileğiyle!';

//Vertify

$LNG['vertifyNoUserFound']			= 'Hata!';
$LNG['vertifyAdminMessage']			= 'Kullanıcı adı "%s" aktif!';


//lostpassword
$LNG['passwordInfo']				= 'Eğer şifreni unuttuysan, Kullanıcı adı ve kayıtlı email adresini sağlamalısın.';
$LNG['passwordUsername']			= 'Kullanıcı Adı';
$LNG['passwordMail']				= 'E-Mail';
$LNG['passwordCaptcha']				= 'Güvenlik Kodu';
$LNG['passwordSubmit']				= 'Gönder';
$LNG['passwordErrorUsernameEmpty']	= 'Kullanıcı adı girmediniz!';
$LNG['passwordErrorMailEmpty']		= 'Yanlış e-mail adresi girdiniz!';
$LNG['passwordErrorUnknown']		= 'Girdiğiniz bilgiler veri tabanında mevcut değil.';
$LNG['passwordErrorOnePerDay']		= 'Bu hesap için şifre son 24 saat içinde talep edildi. Tekrar şifre talep için 24 saat beklemeniz gerekmekte ';

$LNG['passwordValidMailTitle']		= 'Şifremi unuttum : %s';
$LNG['passwordValidMailSend']		= 'Email gönderildi.';

$LNG['passwordValidInValid']		= 'Hatalı Islem!';
$LNG['passwordChangedMailSend']		= 'Yeni şifrenizi mail adresinize gönderdik.';
$LNG['passwordChangedMailTitle']	= ' %s evrenindeki yeni şifreniz :';

$LNG['passwordBack']				= 'Geri';
$LNG['passwordNext']				= 'Ileri';

//case default

$LNG['login_error_1']				= 'Kullanıcı adı yada şifre yanlış!';
$LNG['login_error_2']				= 'Başkası farklı bir bilgisayardan bu hesaba girdi, ya da IP adresiniz değişti!';
$LNG['login_error_3']				= 'Oturumunuz sonlandı!';
$LNG['login_error_4']				= 'Sorun oluştu. Lütfen tekrar deneyiniz!';

//Rules
$LNG['rulesHeader']					= 'Kurallar';

//NEWS
$LNG['news_overview']				= 'Haberler';
$LNG['news_from']					= 'On %s by %s';
$LNG['news_does_not_exist']			= 'Yeni haber yok!';

//Impressum
$LNG['disclamerLabelAddress']		= 'Adres:';
$LNG['disclamerLabelPhone']			= 'Telefon:';
$LNG['disclamerLabelMail']			= 'Destek Email:';
$LNG['disclamerLabelNotice']		= 'Ayrıntılı bilgi';


 //Giris Sayfasi
 $LNG['Browser']				= 'Önerilen browser.';
