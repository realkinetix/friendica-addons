<?php
/**
 * Name: JSXC (XMPP Chat Client)
 * Description: Embedded XMPP (Jabber) client
 * Version: 0.1
 * Author: Adam Clark <https://social.isurf.ca/profile/kinetix>
 * Based entirely on the XMPP addon by Michael Vogel <https://pirati.ca/profile/heluecht>
 */

use Friendica\App;
use Friendica\Core\Config;
use Friendica\Core\Hook;
use Friendica\Core\L10n;
use Friendica\Core\PConfig;
use Friendica\Core\Renderer;
use Friendica\Util\Strings;

function jsxc_install()
{
	Hook::register('addon_settings', 'addon/jsxc/jsxc.php', 'jsxc_addon_settings');
	Hook::register('addon_settings_post', 'addon/jsxc/jsxc.php', 'jsxc_addon_settings_post');
	Hook::register('page_end', 'addon/jsxc/jsxc.php', 'jsxc_script');
	Hook::register('logged_in', 'addon/jsxc/jsxc.php', 'jsxc_login');
}

function jsxc_uninstall()
{
	Hook::unregister('addon_settings', 'addon/jsxc/jsxc.php', 'jsxc_addon_settings');
	Hook::unregister('addon_settings_post', 'addon/jsxc/jsxc.php', 'jsxc_addon_settings_post');
	Hook::unregister('page_end', 'addon/jsxc/jsxc.php', 'jsxc_script');
	Hook::unregister('logged_in', 'addon/jsxc/jsxc.php', 'jsxc_login');
}

function jsxc_addon_settings_post()
{
	if (!local_user() || empty($_POST['jsxc-settings-submit'])) {
		return;
	}

	PConfig::set(local_user(), 'jsxc', 'enabled', defaults($_POST, 'jsxc_enabled', false));
	PConfig::set(local_user(), 'jsxc', 'individual', defaults($_POST, 'jsxc_individual', false));
	PConfig::set(local_user(), 'jsxc', 'bosh_proxy', defaults($_POST, 'jsxc_bosh_proxy', ''));

	info(L10n::t('XMPP settings updated.') . EOL);
}

function jsxc_addon_settings(App $a, &$s)
{
	if (!local_user()) {
		return;
	}

	/* Add our stylesheet to the jsxc so we can make our settings look nice */

	$a->page['htmlhead'] .= '<link rel="stylesheet"  type="text/css" href="' . $a->getBaseURL() . '/addon/jsxc/jsxc.css' . '" media="all" />' . "\r\n";

	/* Get the current state of our config variable */

	$enabled = intval(PConfig::get(local_user(), 'jsxc', 'enabled'));
	$enabled_checked = (($enabled) ? ' checked="checked" ' : '');

	$individual = intval(PConfig::get(local_user(), 'jsxc', 'individual'));
	$individual_checked = (($individual) ? ' checked="checked" ' : '');

	$bosh_proxy = PConfig::get(local_user(), "jsxc", "bosh_proxy");

	/* Add some HTML to the existing form */
	$s .= '<span id="settings_jsxc_inflated" class="settings-block fakelink" style="display: block;" onclick="openClose(\'settings_jsxc_expanded\'); openClose(\'settings_jsxc_inflated\');">';
	$s .= '<h3>' . L10n::t('XMPP-Chat (Jabber)') . '</h3>';
	$s .= '</span>';
	$s .= '<div id="settings_jsxc_expanded" class="settings-block" style="display: none;">';
	$s .= '<span class="fakelink" onclick="openClose(\'settings_jsxc_expanded\'); openClose(\'settings_jsxc_inflated\');">';
	$s .= '<h3>' . L10n::t('XMPP-Chat (Jabber)') . '</h3>';
	$s .= '</span>';

	$s .= '<div id="jsxc-settings-wrapper">';
	$s .= '<label id="jsxc-enabled-label" for="jsxc-enabled">' . L10n::t('Enable Webchat') . '</label>';
	$s .= '<input id="jsxc-enabled" type="checkbox" name="jsxc_enabled" value="1" ' . $enabled_checked . '/>';
	$s .= '<div class="clear"></div>';

	if (Config::get("jsxc", "central_userbase")) {
		$s .= '<label id="jsxc-individual-label" for="jsxc-individual">' . L10n::t('Individual Credentials') . '</label>';
		$s .= '<input id="jsxc-individual" type="checkbox" name="jsxc_individual" value="1" ' . $individual_checked . '/>';
		$s .= '<div class="clear"></div>';
	}

	if (!Config::get("jsxc", "central_userbase") || PConfig::get(local_user(), "jsxc", "individual")) {
		$s .= '<label id="jsxc-bosh-proxy-label" for="jsxc-bosh-proxy">' . L10n::t('Jabber BOSH host') . '</label>';
		$s .= ' <input id="jsxc-bosh-proxy" type="text" name="jsxc_bosh_proxy" value="' . $bosh_proxy . '" />';
		$s .= '<div class="clear"></div>';
	}

	$s .= '</div>';

	/* provide a submit button */

	$s .= '<div class="settings-submit-wrapper" ><input type="submit" name="jsxc-settings-submit" class="settings-submit" value="' . L10n::t('Save Settings') . '" /></div></div>';
}

function jsxc_login()
{
	if (empty($_SESSION['allow_api'])) {
		$password = Strings::getRandomHex(16);
		PConfig::set(local_user(), 'jsxc', 'password', $password);
	}
}

function jsxc_addon_admin(App $a, &$o)
{
	$t = Renderer::getMarkupTemplate('admin.tpl', 'addon/jsxc/');

	$o = Renderer::replaceMacros($t, [
		'$submit' => L10n::t('Save Settings'),
		'$bosh_proxy' => ['bosh_proxy', L10n::t('Jabber BOSH host'), Config::get('jsxc', 'bosh_proxy'), ''],
		'$central_userbase' => ['central_userbase', L10n::t('Use central userbase'), Config::get('jsxc', 'central_userbase'), L10n::t('If enabled, users will automatically login to an ejabberd server that has to be installed on this machine with synchronized credentials via the "auth_ejabberd.php" script.')],
	]);
}

function jsxc_addon_admin_post()
{
	$bosh_proxy = (!empty($_POST['bosh_proxy']) ? trim($_POST['bosh_proxy']) : '');
	$central_userbase = (!empty($_POST['central_userbase']) ? intval($_POST['central_userbase']) : false);

	Config::set('jsxc', 'bosh_proxy', $bosh_proxy);
	Config::set('jsxc', 'central_userbase', $central_userbase);

	info(L10n::t('Settings updated.') . EOL);
}

function jsxc_script(App $a)
{
	jsxc_converse($a);
}

function jsxc_converse(App $a)
{
	if (!local_user()) {
		return;
	}

	if (defaults($_GET, "mode", '') == "minimal") {
		return;
	}

	if ($a->is_mobile || $a->is_tablet) {
		return;
	}

	if (!PConfig::get(local_user(), "jsxc", "enabled")) {
		return;
	}

	if (in_array($a->query_string, ["admin/federation/"])) {
		return;
	}

	$a->page['htmlhead'] .= '<link type="text/css" rel="stylesheet" media="screen" href="addon/jsxc/converse/css/converse.css" />' . "\n";
	$a->page['htmlhead'] .= '<script src="addon/jsxc/converse/builds/converse.min.js"></script>' . "\n";

	if (Config::get("jsxc", "central_userbase") && !PConfig::get(local_user(), "jsxc", "individual")) {
		$bosh_proxy = Config::get("jsxc", "bosh_proxy");

		$password = PConfig::get(local_user(), "jsxc", "password", '', true);

		if ($password == "") {
			$password = Strings::getRandomHex(16);
			PConfig::set(local_user(), "jsxc", "password", $password);
		}

		$jid = $a->user["nickname"] . "@" . $a->getHostName() . "/converse-" . Strings::getRandomHex(5);

		$auto_login = "auto_login: true,
			authentication: 'login',
			jid: '$jid',
			password: '$password',
			allow_logout: false,";
	} else {
		$bosh_proxy = PConfig::get(local_user(), "jsxc", "bosh_proxy");

		$auto_login = "";
	}

	if ($bosh_proxy == "") {
		return;
	}

	if (in_array($a->argv[0], ["manage", "logout"])) {
		$additional_commands = "converse.user.logout();\n";
	} else {
		$additional_commands = "";
	}

	$on_ready = "";

	$initialize = "converse.initialize({
					bosh_service_url: '$bosh_proxy',
					keepalive: true,
					message_carbons: false,
					forward_messages: false,
					play_sounds: true,
					sounds_path: 'addon/jsxc/converse/sounds/',
					roster_groups: false,
					show_controlbox_by_default: false,
					show_toolbar: true,
					allow_contact_removal: false,
					allow_registration: false,
					hide_offline_users: true,
					allow_chat_pending_contacts: false,
					allow_dragresize: true,
					auto_away: 0,
					auto_xa: 0,
					csi_waiting_time: 300,
					auto_reconnect: true,
					$auto_login
					xhr_user_search: false
				});\n";

	$a->page['htmlhead'] .= "<script>
					require(['converse'], function (converse) {
						$initialize
						converse.listen.on('ready', function (event) {
							$on_ready
						});
						$additional_commands
					});
				</script>";
}
