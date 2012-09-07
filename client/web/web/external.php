<?php
/**
 * Copyright (C) 2011-2012 Ulteo SAS
 * http://www.ulteo.com
 * Author Jeremy DESVAGES <jeremy@ulteo.com> 2011
 * Author Julien LANGLOIS <julien@ulteo.com> 2012
 * Author David PHAM-VAN <d.pham-van@ulteo.com> 2012
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; version 2
 * of the License.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 **/

require_once(dirname(__FILE__).'/includes/core.inc.php');


if (array_key_exists('REQUEST_METHOD', $_SERVER) && $_SERVER['REQUEST_METHOD'] == 'POST') {
	$_SESSION['last_request'] = array();
	foreach($_POST as $k => $v)
		$_SESSION['last_request'][$k] = $v;
	
	header('Location: external.php');
	die();
}

if (array_key_exists('last_request', $_SESSION)) {
	foreach($_SESSION['last_request'] as $k => $v)
		$_REQUEST[$k] = $v;
	
	unset($_SESSION['last_request']);
}

$big_image_map = false;
if (get_ie_version() > 7 && file_exists(WEB_CLIENT_ROOT . "/media/image/uovd.png")) {
	$big_image_map = true;
}

if (!$big_image_map) {
	$logo_size = getimagesize(dirname(__FILE__).'/media/image/ulteo.png');
	if ($logo_size === false)
		$logo_size = "";
	else
		$logo_size = $logo_size[3];
}

if (OPTION_FORCE_LANGUAGE !== true && array_key_exists('language', $_REQUEST)) {
	$available_languages = get_available_languages();

	if (language_is_supported($available_languages, $_REQUEST['language'])) {
		$user_language = $_REQUEST['language'];
		if (OPTION_FORCE_KEYMAP !== true)
			$user_keymap = $user_language;
	}
}

list($translations, $js_translations) = get_available_translations($user_language);

$first = false;
if (array_key_exists('ovd-client', $_SESSION) && array_key_exists('sessionmanager', $_SESSION['ovd-client'])) {
	$sm = $_SESSION['ovd-client']['sessionmanager'];
	
	// Check if session still exist SM side
	$dom = new DomDocument('1.0', 'utf-8');
	$buf = @$dom->loadXML($sm->query('session_status.php'));
	if (! $buf)
		die('Invalid XML from Session Manager');
	
	if (! $dom->hasChildNodes())
		die('Invalid XML from Session Manager');
	
	$session_nodes = $dom->getElementsByTagName('session');
	if ($session_nodes->length == 0)
		$first = true;
	elseif ($session_nodes->length > 0) {
		$session_node = $session_nodes->item(0);
		if (in_array($session_node->getAttribute('status'), array('unknown', 'error', 'wait_destroy', 'destroyed')))
			$first = true;
	}
}
else
	$first = true;

if ($first === true) {
	$dom = new DomDocument('1.0', 'utf-8');

	$session_node = $dom->createElement('session');
	if (array_key_exists('mode', $_REQUEST))
		$session_node->setAttribute('mode', $_REQUEST['mode']);
	if (array_key_exists('language', $_REQUEST))
		$session_node->setAttribute('language', $_REQUEST['language']);
	$user_node = $dom->createElement('user');
	if (array_key_exists('login', $_REQUEST))
		$user_node->setAttribute('login', $_REQUEST['login']);
	if (array_key_exists('password', $_REQUEST))
		$user_node->setAttribute('password', $_REQUEST['password']);
	if (array_key_exists('token', $_REQUEST))
		$user_node->setAttribute('token', $_REQUEST['token']);
	$session_node->appendChild($user_node);
	
	if ($_REQUEST['mode'] == 'desktop' && array_key_exists('app', $_REQUEST))
		$session_node->setAttribute('no_desktop', '1');
	
	$dom->appendChild($session_node);

	$sm_host = @SESSIONMANAGER_HOST; // If the WebClient is not linked to a SessionManager, JavaScript object will return an 'Usage: missing "sessionmanager_host" parameter' error
	$_SESSION['ovd-client']['sessionmanager_url'] = 'https://'.$sm_host.'/ovd/client';
	$sessionmanager_url = $_SESSION['ovd-client']['sessionmanager_url'];
	
	$sm = new SessionManager($sessionmanager_url);
	$_SESSION['ovd-client']['sessionmanager'] = $sm;

	$sm->query_post_xml('auth.php', $dom->saveXML());

	$_SESSION['ovd-client']['start_app'] = array();
}

if (array_key_exists('app', $_REQUEST)) {
	$order = array('id' => $_REQUEST['app']);

	if (array_key_exists('file', $_REQUEST)) {
		$args = array();
		$args['path'] = $_REQUEST['file'];
		$args['share'] = base64_decode($_REQUEST['file_share']);
		$args['type'] = $_REQUEST['file_type'];

		$order['file'] = $args;
	}

	$_SESSION['ovd-client']['start_app'][] = $order;
}

$rdp_input_unicode = null;
if (defined('RDP_INPUT_METHOD'))
	$rdp_input_unicode = RDP_INPUT_METHOD;

$local_integration = (defined('PORTAL_LOCAL_INTEGRATION') && (PORTAL_LOCAL_INTEGRATION === true));

if ($debug_mode === false && array_key_exists('debug', $_REQUEST))
	$debug_mode = true;

$headers = apache_request_headers();
$gateway_first = (is_array($headers) && array_key_exists('OVD-Gateway', $headers));

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<title>Ulteo Open Virtual Desktop</title>

		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

		<meta http-equiv="X-UA-Compatible" content="IE=Edge" />

		<link rel="shortcut icon" href="media/image/favicon.ico" />
		<link rel="shortcut icon" type="image/png" href="media/image/favicon.png" />

<?php if (file_exists(WEB_CLIENT_ROOT . "/media/style/uovd.css")) { ?>
		<link rel="stylesheet" type="text/css" href="media/style/uovd.css" />
<?php } else { ?>
		<link rel="stylesheet" type="text/css" href="media/script/lib/nifty/niftyCorners.css" />
		<link rel="stylesheet" type="text/css" href="media/style/images.css" />
		<link rel="stylesheet" type="text/css" href="media/style/common.css" />
<?php } ?>

<?php if (file_exists(WEB_CLIENT_ROOT . "/media/script/uovd.js")) { ?>
		<script type="text/javascript" src="media/script/uovd.js" charset="utf-8"></script>
<?php } else { ?>
		<script type="text/javascript" src="media/script/lib/prototype/prototype.js" charset="utf-8"></script>
		<script type="text/javascript" src="media/script/lib/scriptaculous/effects.js" charset="utf-8"></script>
		<script type="text/javascript" src="media/script/lib/scriptaculous/extensions.js" charset="utf-8"></script>
		<script type="text/javascript" src="media/script/lib/nifty/niftyCorners.js" charset="utf-8"></script>
		<script type="text/javascript" src="media/script/common.js" charset="utf-8"></script>
		<script type="text/javascript" src="media/script/daemon.js" charset="utf-8"></script>
		<script type="text/javascript" src="media/script/daemon_desktop.js" charset="utf-8"></script>
		<script type="text/javascript" src="media/script/daemon_applications.js" charset="utf-8"></script>
		<script type="text/javascript" src="media/script/daemon_external.js" charset="utf-8"></script>
		<script type="text/javascript" src="media/script/server.js" charset="utf-8"></script>
		<script type="text/javascript" src="media/script/application.js" charset="utf-8"></script>
		<script type="text/javascript" src="media/script/JavaTester.js" charset="utf-8"></script>
		<script type="text/javascript" src="media/script/Logger.js" charset="utf-8"></script>
		<script type="text/javascript" src="media/script/timezones.js" charset="utf-8"></script>
<?php } ?>

		<script type="text/javascript" src="media/script/uovd_ext_client.js" charset="utf-8"></script>

		<script type="text/javascript">
			var big_image_map = <?php echo ($big_image_map?'true':'false'); ?>;

			NiftyLoad = function() {
				Nifty('div.rounded');
			}

			var i18n = new Hash();
<?php		foreach ($js_translations as $id => $string)
			echo 'i18n.set(\''.$id.'\', \''.str_replace('\'', '\\\'', $string).'\');'."\n";
?>
			var i18n_tmp = new Hash();
<?php		foreach ($translations as $id => $string) 
			echo 'i18n_tmp.set(\''.$id.'\', \''.str_replace('\'', '\\\'', $string).'\');'."\n";
?>

			var SESSIONMANAGER = '<?php echo SESSIONMANAGER_HOST; ?>';
			var GATEWAY_FIRST_MODE = <?php echo (($gateway_first === true)?'true':'false'); ?>;
			var user_keymap = '<?php echo $user_keymap; ?>';
			var OPTION_KEYMAP_AUTO_DETECT = <?php echo ((OPTION_KEYMAP_AUTO_DETECT === true)?'true':'false'); ?>;

			<?php
				if (array_key_exists('mode', $_REQUEST) && $_REQUEST['mode'] == 'applications' && ! $first) {
			?>
					Event.observe(window, 'load', function() {
						window.close();
					});
			<?php
				} else {
			?>
					var daemon;
					var client_language = '<?php echo $user_language; ?>';
					var rdp_input_method = <?php echo (($rdp_input_unicode == null)?'null':'\''.$rdp_input_unicode.'\''); ?>;
					var local_integration = <?php echo (($local_integration === true)?'true':'false'); ?>;
					var debug_mode = <?php echo (($debug_mode === true)?'true':'false'); ?>;

					Event.observe(window, 'load', function() {
						if ('<?php echo $_REQUEST['mode']; ?>' == 'desktop')
							new Effect.Center($('splashContainer'));
						new Effect.Center($('endContainer'));

						$('desktopModeContainer').hide();
						$('desktopAppletContainer').hide();

						$('applicationsModeContainer').hide();
						$('applicationsAppletContainer').hide();

						applyTranslations(i18n_tmp);
						startExternalSession('<?php echo $_REQUEST['mode']; ?>');
					});
			<?php
				}
			?>
		</script>
	</head>

	<body style="margin: 10px; background: #ddd; color: #333;">
		<div id="lockWrap" style="display: none;">
		</div>

		<div style="background: #2c2c2c; width: 0px; height: 0px;">
			<div id="errorWrap" class="rounded" style="display: none;">
			</div>
			<div id="okWrap" class="rounded" style="display: none;">
			</div>
			<div id="infoWrap" class="rounded" style="display: none;">
			</div>
		</div>

		<div id="testJava">
		</div>

		<div style="background: #2c2c2c; width: 0px; height: 0px;">
			<div id="systemTestWrap" class="rounded" style="display: none;">
				<div id="systemTest" class="rounded">
					<table style="width: 100%; margin-left: auto; margin-right: auto;" border="0" cellspacing="1" cellpadding="3">
						<tr>
							<td style="text-align: left; vertical-align: top;">
								<strong><span id="system_compatibility_check_1_gettext">&nbsp;</span></strong>
								<div style="margin-top: 15px;">
									<p id="system_compatibility_check_2_gettext">&nbsp;</p>
									<p id="system_compatibility_check_3_gettext">&nbsp;</p>
								</div>
							</td>
							<td style="width: 32px; height: 32px; text-align: right; vertical-align: top;">
								<?php if (!$big_image_map) { ?>
								<img src="media/image/rotate.gif" width="32" height="32" alt="" title="" />
								<?php } else { ?>
								<div class="image_rotate_gif"></div>
								<?php } ?>
							</td>
						</tr>
					</table>
				</div>
			</div>

			<div id="systemTestErrorWrap" class="rounded" style="display: none;">
				<div id="systemTestError" class="rounded">
					<table style="width: 100%; margin-left: auto; margin-right: auto;" border="0" cellspacing="1" cellpadding="3">
						<tr>
							<td style="text-align: left; vertical-align: middle;">
								<strong><span id="system_compatibility_error_1_gettext">&nbsp;</span></strong>
								<div id="systemTestError1" style="margin-top: 15px; display: none;">
									<p id="system_compatibility_error_2_gettext">&nbsp;</p>
									<p id="system_compatibility_error_3_gettext">&nbsp;</p>
								</div>

								<div id="systemTestError2" style="margin-top: 15px; display: none;">
									<p id="system_compatibility_error_4_gettext">&nbsp;</p>
								</div>

								<p id="system_compatibility_error_5_gettext">&nbsp;</p>
							</td>
							<td style="width: 32px; height: 32px; text-align: right; vertical-align: top;">
								<?php if (!$big_image_map) { ?>
								<img src="media/image/error.png" width="32" height="32" alt="" title="" />
								<?php } else { ?>
								<div class="image_error_png"></div>
								<?php } ?>
							</td>
						</tr>
					</table>
				</div>
			</div>
		</div>

		<div id="splashContainer" class="rounded">
			<table style="width: 100%; padding: 10px;" border="0" cellspacing="0" cellpadding="0">
				<tr>
					<td style="text-align: center;" colspan="3">
						<?php if (!$big_image_map) { ?>
						<img src="media/image/ulteo.png" <?php echo $logo_size; ?> alt="" title="" />
						<?php } else { ?>
						<div class="image_ulteo_png"></div>
						<?php } ?>
					</td>
				</tr>
				<tr>
					<td style="text-align: left; vertical-align: middle; margin-top: 15px;">
						<span style="font-size: 1.35em; font-weight: bold; color: #686868;"><?php echo _('Do not close this Ulteo OVD window!'); ?></span>
					</td>
					<td style="width: 20px"></td>
					<td style="text-align: left; vertical-align: middle;">
						<?php if (!$big_image_map) { ?>
						<img src="media/image/rotate.gif" width="32" height="32" alt="" title="" />
						<?php } else { ?>
						<div class="image_rotate_gif"></div>
						<?php } ?>
					</td>
				</tr>
			</table>
		</div>

		<div id="endContainer" class="rounded" style="display: none;">
			<table style="width: 100%; padding: 10px;" border="0" cellspacing="0" cellpadding="0">
				<tr>
					<td style="text-align: center;">
						<?php if (!$big_image_map) { ?>
						<img src="media/image/ulteo.png" <?php echo $logo_size; ?> alt="" title="" />
						<?php } else { ?>
						<div class="image_ulteo_png"></div>
						<?php } ?>
					</td>
				</tr>
				<tr>
					<td style="text-align: center; vertical-align: middle; margin-top: 15px;" id="endContent">
					</td>
				</tr>
			</table>
		</div>

		<div id="desktopModeContainer" style="display: none;">
			<div id="desktopAppletContainer" style="display: none;">
			</div>
		</div>

		<div id="applicationsModeContainer" style="display: none;">
			<div id="applicationsAppletContainer" style="display: none;">
			</div>
		</div>

		<div id="debugContainer" class="no_debug info warning error" style="display: none;">
		</div>

		<div id="debugLevels" style="display: none;">
			<span class="debug"><input type="checkbox" id="level_debug" onclick="Logger.toggle_level('debug');" value="10" /> Debug</span>
			<span class="info"><input type="checkbox" id="level_info" onclick="Logger.toggle_level('info');" value="20" checked="checked" /> Info</span>
			<span class="warning"><input type="checkbox" id="level_warning" onclick="Logger.toggle_level('warning');" value="30" checked="checked" /> Warning</span>
			<span class="error"><input type="checkbox" id="level_error" onclick="Logger.toggle_level('error');" value="40" checked="checked" /> Error</span><br />
			<input type="button" onclick="Logger.clear(); return false;" value="Clear" />
		</div>
	</body>
</html>
