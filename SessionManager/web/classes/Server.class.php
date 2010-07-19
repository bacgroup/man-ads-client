<?php
/**
 * Copyright (C) 2008-2010 Ulteo SAS
 * http://www.ulteo.com
 * Author Laurent CLOUET <laurent@ulteo.com>
 * Author Jeremy DESVAGES <jeremy@ulteo.com>
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
require_once(dirname(__FILE__).'/../includes/core.inc.php');

class Server {
	public $fqdn = NULL;

	public $status = NULL;
	public $registered = NULL;
	public $locked = NULL;
	public $type = NULL;
	public $version = NULL;
	public $web_port = 1112;
	public $cpu_model = NULL;
	public $cpu_nb_cores = NULL;
	public $cpu_load = NULL;
	public $ram_total = NULL;
	public $ram_used = NULL;

	public $roles = array();

	public function __construct($fqdn_) {
// 		Logger::debug('main', 'Starting Server::__construct for \''.$fqdn_.'\'');

		$this->fqdn = $fqdn_;
	}

	public function __toString() {
		return 'Server(\''.$this->fqdn.'\', \''.$this->status.'\', \''.$this->registered.'\', \''.$this->locked.'\', \''.$this->type.'\', \''.$this->version.'\', \''.$this->web_port.'\', \''.$this->cpu_model.'\', \''.$this->cpu_nb_cores.'\', \''.$this->cpu_load.'\', \''.$this->ram_total.'\', \''.$this->ram_used.'\')';
	}

	public function hasAttribute($attrib_) {
// 		Logger::debug('main', 'Starting Server::hasAttribute for \''.$this->fqdn.'\' attribute '.$attrib_);

		if (! isset($this->$attrib_) || is_null($this->$attrib_))
			return false;

		return true;
	}

	public function getAttribute($attrib_) {
// 		Logger::debug('main', 'Starting Server::getAttribute for \''.$this->fqdn.'\' attribute '.$attrib_);

		if (! $this->hasAttribute($attrib_))
			return false;

		return $this->$attrib_;
	}

	public function setAttribute($attrib_, $value_) {
// 		Logger::debug('main', 'Starting Server::setAttribute for \''.$this->fqdn.'\' attribute '.$attrib_.' value '.$value_);

		$this->$attrib_ = $value_;

		return true;
	}

	public function uptodateAttribute($attrib_) {
// 		Logger::debug('main', 'Starting Server::uptodateAttribute for \''.$this->fqdn.'\' attribute '.$attrib_);

		$buf = Abstract_Server::uptodate($this);

		return $buf;
	}

	public function getConfiguration() {
		if (! $this->isOnline()) {
			Logger::debug('main', 'Server::getConfiguration server "'.$this->fqdn.':'.$this->web_port.'" is not online');
			return false;
		}

		$xml = query_url($this->getBaseURL().'/server/configuration');
		if (! $xml) {
			$this->isUnreachable();
			Logger::error('main', 'Server::getConfiguration server \''.$this->fqdn.'\' is unreachable');
			return false;
		}

		$dom = new DomDocument('1.0', 'utf-8');

		$buf = @$dom->loadXML($xml);
		if (! $buf) {
			Logger::error('main', 'Server::getConfiguration - Unable to load XML');
			return false;
		}

		if (! $dom->hasChildNodes()) {
			Logger::error('main', 'Server::getConfiguration - Invalid XML (no child nodes)');
			return false;
		}

		$node = $dom->getElementsByTagname('configuration')->item(0);
		if (is_null($node)) {
			Logger::error('main', 'Server::getConfiguration - Invalid XML (no configuration node)');
			return false;
		}

		if (! $node->hasAttribute('type')) {
			Logger::error('main', 'Server::getConfiguration - Invalid XML (no attribute type in configuration node)');
			return false;
		}

		if (! $node->hasAttribute('version')) {
			Logger::error('main', 'Server::getConfiguration - Invalid XML (no attribute version in configuration node)');
			return false;
		}

		if (! $node->hasAttribute('ram')) {
			Logger::error('main', 'Server::getConfiguration - Invalid XML (no attribute ram in configuration node)');
			return false;
		}

		$this->type = $node->getAttribute('type');
		$this->version = $node->getAttribute('version');
		$this->ram_total = $node->getAttribute('ram');

		$this->ulteo_system = false;
		if ($node->hasAttribute('ulteo_system') && $node->getAttribute('ulteo_system') == 'true')
			$this->ulteo_system = true;

		$node = $node->getElementsByTagname('cpu')->item(0);
		if (is_null($node)) {
			Logger::error('main', 'Server::getConfiguration - Invalid XML (no cpu node)');
			return false;
		}

		if (! $node->hasAttribute('nb_cores')) {
			Logger::error('main', 'Server::getConfiguration - Invalid XML (no attribute nb_cores in cpu node)');
			return false;
		}

		$this->cpu_nb_cores = $node->getAttribute('nb_cores');
		$this->cpu_model = $node->firstChild->nodeValue;

		$node = $dom->getElementsByTagname('configuration')->item(0);
		$nodes = $node->getElementsByTagname('role');
		if (count($nodes) > 0) {
			$this->roles = array();

			foreach ($nodes as $node) {
				if (! $node->hasAttribute('name')) {
					Logger::error('main', 'Server::getConfiguration - Invalid XML (no attribute name in role node)');
					return false;
				}

				$this->roles[$node->getAttribute('name')] = true;
			}
		}

		return true;
	}

	public function getBaseURL() {
		return 'http://'.$this->fqdn.':'.$this->web_port;
	}

	public function getWebservicesBaseURL() {
		return $this->getBaseURL();
	}

	public function isOK() {
		return $this->getConfiguration();
	}

	public function isAuthorized() {
		Logger::debug('main', 'Starting Server::isAuthorized for \''.$this->fqdn.'\'');

		$prefs = Preferences::getInstance();
		if (! $prefs) {
			Logger::critical('main', 'get Preferences failed in '.__FILE__.' line '.__LINE__);
			return false;
		}

		$buf = $prefs->get('general', 'slave_server_settings');
		$authorized_fqdn = $buf['authorized_fqdn'];
		$fqdn_private_address = $buf['fqdn_private_address'];
		$disable_fqdn_check = $buf['disable_fqdn_check'];

		$address = $_SERVER['REMOTE_ADDR'];
		$name = $this->fqdn;

		$buf = false;
		foreach ($authorized_fqdn as $fqdn) {
			$fqdn = str_replace('*', '.*', str_replace('.', '\.', $fqdn));

			if (preg_match('/'.$fqdn.'/', $name))
				$buf = true;
		}

		if (! $buf) {
			Logger::warning('main', '"'.$this->fqdn.'": server is NOT authorized! ('.$buf.')');
			return false;
		}

		if (preg_match('/[0-9]{1,3}(\.[0-9]{1,3}){3}/', $name)) {// if IP?
			$ret = ($name == $address);
			if (! $ret) {
				Logger::error('main', "FQDN does NOT match ApplicationServer's source address (source='$address' sent='$name')");
			}
			return $ret;
		}

		if ($disable_fqdn_check == 1)
			return true;

		$reverse = @gethostbyaddr($address);
		if (($reverse == $name) || (isset($fqdn_private_address[$name]) && $fqdn_private_address[$name] == $address))
			return true;

		Logger::warning('main', '"'.$this->fqdn.'": reverse DNS is invalid! ('.$reverse.')');

		return false;
	}

	public function register() {
		Logger::debug('main', 'Starting Server::register for \''.$this->fqdn.'\'');

		if (! $this->isOnline()) {
			Logger::debug('main', 'Server::register server "'.$this->fqdn.':'.$this->web_port.'" is not online');
			return false;
		}

		if (! $this->isOK()) {
			Logger::debug('main', 'Server::register server "'.$this->fqdn.':'.$this->web_port.'" is not OK');
			return false;
		}

		$this->setAttribute('registered', true);

		$this->updateApplications();
		$this->updateNetworkFolders();

		return true;
	}

	public function isOnline() {
		Logger::debug('main', 'Starting Server::isOnline for \''.$this->fqdn.'\'');

		$warn = false;

		if (! $this->hasAttribute('status') || ! $this->uptodateAttribute('status')) {
			$warn = true;
			$this->getStatus();
		}

		if ($this->hasAttribute('status') && $this->getAttribute('status') == 'ready')
			return true;

		if ($warn === true && $this->getAttribute('locked') == 0) {
			popup_error('"'.$this->fqdn.'": '._('is NOT online!'));
			Logger::error('main', '"'.$this->fqdn.'": is NOT online!');
		}

		$this->isNotReady();

		return false;
	}

	public function isUnreachable() {
		Logger::debug('main', 'Starting Server::isUnreachable for \''.$this->fqdn.'\'');

		if ($this->getAttribute('status') == 'broken') {
			Logger::debug('main', 'Server::isUnreachable server "'.$this->fqdn.':'.$this->web_port.'" is already "broken"');
			return false;
		}

		$ev = new ServerStatusChanged(array(
			'fqdn'		=>	$this->fqdn,
			'status'	=>	ServerStatusChanged::$UNREACHABLE
		));

		Logger::critical('main', 'Server "'.$this->fqdn.':'.$this->web_port.'" is unreachable, status switched to "broken"');
		$this->setAttribute('status', 'broken');

		$ev->emit();

		$this->isNotReady();

		Abstract_Server::modify($this);

		return true;
	}

	public function isNotReady() {
		Logger::debug('main', 'Starting Server::isNotReady for \''.$this->fqdn.'\'');

		if ($this->getAttribute('status') == 'ready') {
			Logger::debug('main', 'Server::isNotReady server "'.$this->fqdn.':'.$this->web_port.'" is "ready"');
			return false;
		}

		$sessions = Abstract_Session::getByServer($this->fqdn);
		foreach ($sessions as $session)
			Abstract_Session::delete($session->id);

		$tasks = Abstract_Task::load_by_server($this->fqdn);
		foreach ($tasks as $task)
			Abstract_Task::delete($task->id);

		$prefs = Preferences::getInstance();
		if (! $prefs) {
			Logger::critical('main', 'get Preferences failed in '.__FILE__.' line '.__LINE__);
			return false;
		}

		$buf = $prefs->get('general', 'slave_server_settings');
		if ($buf['action_when_as_not_ready'] == 1)
			if ($this->getAttribute('locked') === false)
				$this->setAttribute('locked', true);

		Abstract_Server::modify($this);

		return true;
	}

	public function returnedError() {
		Logger::debug('main', 'Starting Server::returnedError for \''.$this->fqdn.'\'');

		if ($this->getAttribute('status') == 'broken') {
			Logger::debug('main', 'Server::returnedError server "'.$this->fqdn.':'.$this->web_port.'" is already "broken"');
			return false;
		}

		Logger::error('main', 'Server "'.$this->fqdn.':'.$this->web_port.'" returned an ERROR, status switched to "broken"');
		$this->setAttribute('status', 'broken');

		$this->isNotReady();
		return true;
	}

	public function getStatus() {
		if ($this->getAttribute('status') != 'ready') {
			$this->isNotReady();
			return false;
		}

		$xml = query_url($this->getBaseURL().'/server/status');
		if (! $xml) {
			$this->isUnreachable();
			Logger::error('main', 'Server::getStatus server \''.$this->fqdn.'\' is unreachable');
			return false;
		}

		$dom = new DomDocument('1.0', 'utf-8');

		$buf = @$dom->loadXML($xml);
		if (! $buf)
			return false;

		if (! $dom->hasChildNodes())
			return false;

		$node = $dom->getElementsByTagname('server')->item(0);
		if (is_null($node))
			return false;

		if (! $node->hasAttribute('status'))
			return false;

		if ($node->getAttribute('status') != $this->getAttribute('status'))
			$this->setStatus($node->getAttribute('status'));

		return true;
	}

	public function setStatus($status_) {
		Logger::debug('main', 'Starting Server::setStatus for \''.$this->fqdn.'\'');

		$ev = new ServerStatusChanged(array(
			'fqdn'		=>	$this->fqdn,
			'status'	=>	($status_ == 'ready')?ServerStatusChanged::$ONLINE:ServerStatusChanged::$OFFLINE
		));

		switch ($status_) {
			case 'ready':
				if ($this->getAttribute('status') != 'ready') {
					Logger::info('main', 'Status set to "ready" for server \''.$this->fqdn.'\'');
					$this->setAttribute('status', 'ready');
				}
				break;
			case 'down':
				if ($this->getAttribute('status') != 'down') {
					Logger::warning('main', 'Status set to "down" for server \''.$this->fqdn.'\'');
					$this->setAttribute('status', 'down');
				}
				break;
			case 'broken':
			default:
				if ($this->getAttribute('status') != 'broken') {
					Logger::error('main', 'Status set to "broken" for server \''.$this->fqdn.'\'');
					$this->setAttribute('status', 'broken');
				}
				break;
		}

		$ev->emit();

		Abstract_Server::save($this);

		return true;
	}

	public function stringStatus() {
// 		Logger::debug('main', 'Starting Server::stringStatus for \''.$this->fqdn.'\'');

		$string = '';

		if ($this->getAttribute('locked'))
			$string .= '<span class="msg_unknown">'._('Under maintenance').'</span> ';

		$buf = $this->getAttribute('status');
		if ($buf == 'ready')
			$string .= '<span class="msg_ok">'._('Online').'</span>';
		elseif ($buf == 'down')
			$string .= '<span class="msg_warn">'._('Offline').'</span>';
		elseif ($buf == 'broken')
			$string .= '<span class="msg_error">'._('Broken').'</span>';
		else
			$string .= '<span class="msg_other">'._('Unknown').'</span>';

		return $string;
	}

	public function stringType() {
// 		Logger::debug('main', 'Starting Server::stringType for \''.$this->fqdn.'\'');

		if ($this->hasAttribute('type'))
			return $this->getAttribute('type');

		return _('Unknown');
	}

	public function stringVersion() {
// 		Logger::debug('main', 'Starting Server::stringVersion for \''.$this->fqdn.'\'');

		if ($this->hasAttribute('version'))
			return $this->getAttribute('version');

		return _('Unknown');
	}

	public function getNbMaxSessions() {
		Logger::debug('main', 'Starting Server::getNbMaxSessions for \''.$this->fqdn.'\'');

		return $this->getAttribute('max_sessions');
	}

	public function getNbUsedSessions() {
		Logger::debug('main', 'Starting Server::getNbUsedSessions for \''.$this->fqdn.'\'');

  		$buf = Abstract_Session::countByServer($this->fqdn);

		return count($buf);
	}

	public function getNbAvailableSessions() {
		Logger::debug('main', 'Starting Server::getNbAvailableSessions for \''.$this->fqdn.'\'');

		$max_sessions = $this->getNbMaxSessions();
		$used_sessions = $this->getNbUsedSessions();

		return ($max_sessions-$used_sessions);
	}

	public function getMonitoring() {
		if (! $this->isOnline()) {
			Logger::debug('main', 'Server::getMonitoring server "'.$this->fqdn.':'.$this->web_port.'" is not online');
			return false;
		}

		$xml = query_url($this->getBaseURL().'/server/monitoring');
		if (! $xml) {
			$this->isUnreachable();
			Logger::error('main', 'Server::getMonitoring server \''.$this->fqdn.'\' is unreachable');
			return false;
		}

		$dom = new DomDocument('1.0', 'utf-8');

		$buf = @$dom->loadXML($xml);
		if (! $buf)
			return false;

		if (! $dom->hasChildNodes())
			return false;

		$keys = array();

		$cpu_node = $dom->getElementsByTagname('cpu')->item(0);
		if (is_object($cpu_node)) {
			if ($cpu_node->hasAttribute('load'))
				$keys['cpu_load'] = $cpu_node->getAttribute('load');
		}

		$ram_node = $dom->getElementsByTagname('ram')->item(0);
		if (is_object($ram_node)) {
			if ($ram_node->hasAttribute('used'))
				$keys['ram_used'] = $ram_node->getAttribute('used');
		}

		foreach ($keys as $k => $v)
			$this->setAttribute($k, trim($v));

		return true;
	}

	public function getCpuUsage() {
		Logger::debug('main', 'Starting Server::getCpuUsage for \''.$this->fqdn.'\'');

		$cpu_load = $this->getAttribute('cpu_load');
		$cpu_nb_cores = $this->getAttribute('cpu_nb_cores');

		if ($cpu_nb_cores == 0)
			return false;

		return round(($cpu_load/$cpu_nb_cores)*100);
	}

	public function getRamUsage() {
		Logger::debug('main', 'Starting Server::getRamUsage for \''.$this->fqdn.'\'');

		$ram_used = $this->getAttribute('ram_used');
		$ram_total = $this->getAttribute('ram_total');

		if ($ram_total == 0)
			return false;

		return round(($ram_used/$ram_total)*100);
	}

	public function getSessionUsage() {
		Logger::debug('main', 'Starting Server::getSessionUsage for \''.$this->fqdn.'\'');

		$max_sessions = $this->getNbMaxSessions();
		$used_sessions = $this->getNbUsedSessions();

		if ($max_sessions == 0)
			return false;

		return round(($used_sessions/$max_sessions)*100);
	}

	public function orderDeletion() {
		Logger::debug('main', 'Starting Server::orderDeletion for \''.$this->fqdn.'\'');

		Abstract_Liaison::delete('StaticApplicationServer', NULL, $this->fqdn);

		return true;
	}

	public function getSessionStatus($session_id_) {
		Logger::debug('main', 'Starting Server::getSessionStatus for session \''.$session_id_.'\' on server \''.$this->fqdn.'\'');

		if (! $this->isOnline()) {
			Logger::debug('main', 'Server::getSessionStatus for session \''.$session_id_.'\' server "'.$this->fqdn.':'.$this->web_port.'" is not online');
			return false;
		}

		$xml = query_url($this->getBaseURL().'/aps/session/status/'.$session_id_);
		if (! $xml) {
			$this->isUnreachable();
			Logger::error('main', 'Server::getSessionStatus server \''.$this->fqdn.'\' is unreachable');
			return false;
		}

		$dom = new DomDocument('1.0', 'utf-8');

		$buf = @$dom->loadXML($xml);
		if (! $buf)
			return false;

		if (! $dom->hasChildNodes())
			return false;

		$node = $dom->getElementsByTagname('session')->item(0);
		if (is_null($node))
			return false;

		if (! $node->hasAttribute('id'))
			return false;

		if (! $node->hasAttribute('status'))
			return false;

		$session_status = $node->getAttribute('status');

		return $session_status;
	}

	public function orderSessionDeletion($session_id_) {
		Logger::debug('main', 'Starting Server::orderSessionDeletion for session \''.$session_id_.'\' on server \''.$this->fqdn.'\'');

		if (! $this->isOnline()) {
			Logger::debug('main', 'Server::orderSessionDeletion for session \''.$session_id_.'\' server "'.$this->fqdn.':'.$this->web_port.'" is not online');
			return false;
		}

		$xml = query_url($this->getBaseURL().'/aps/session/destroy/'.$session_id_);
		if (! $xml) {
			$this->isUnreachable();
			Logger::error('main', 'Server::orderSessionDeletion server \''.$this->fqdn.'\' is unreachable');
			return false;
		}

		$dom = new DomDocument('1.0', 'utf-8');

		$buf = @$dom->loadXML($xml);
		if (! $buf)
			return false;

		if (! $dom->hasChildNodes())
			return false;

		$node = $dom->getElementsByTagname('session')->item(0);
		if (is_null($node))
			return false;

		if (! $node->hasAttribute('id'))
			return false;

		if (! $node->hasAttribute('status'))
			return false;

		if ($node->getAttribute('status') != 'destroying')
			return false;

		return true;
	}

	public function userIsLoggedIn($user_login_) {
		$user_login_ .= '_OVD'; //hardcoded

		$dom = new DomDocument('1.0', 'utf-8');

		$node = $dom->createElement('user');
		$node->setAttribute('login', $user_login_);
		$dom->appendChild($node);

		$xml = $dom->saveXML();

		$xml = query_url_post_xml($this->getBaseURL().'/aps/user/loggedin', $xml);
		if (! $xml) {
			$this->isUnreachable();
			Logger::error('main', 'Server::userIsLoggedIn server \''.$this->fqdn.'\' is unreachable');
			return false;
		}

		$dom = new DomDocument('1.0', 'utf-8');

		$buf = @$dom->loadXML($xml);
		if (! $buf)
			return false;

		if (! $dom->hasChildNodes())
			return false;

		$node = $dom->getElementsByTagname('user')->item(0);
		if (is_null($node))
			return false;

		if (! $node->hasAttribute('login'))
			return false;

		if (! $node->hasAttribute('loggedin'))
			return false;

		$loggedin = $node->getAttribute('loggedin');

		return ($loggedin == 'true');
	}

	public function userLogout($user_login_) {
		$user_login_ .= '_OVD'; //hardcoded

		$dom = new DomDocument('1.0', 'utf-8');

		$node = $dom->createElement('user');
		$node->setAttribute('login', $user_login_);
		$dom->appendChild($node);

		$xml = $dom->saveXML();

		$xml = query_url_post_xml($this->getBaseURL().'/aps/user/logout', $xml);
		if (! $xml) {
			$this->isUnreachable();
			Logger::error('main', 'Server::userLogout server \''.$this->fqdn.'\' is unreachable');
			return false;
		}

		$dom = new DomDocument('1.0', 'utf-8');

		$buf = @$dom->loadXML($xml);
		if (! $buf)
			return false;

		if (! $dom->hasChildNodes())
			return false;

		$node = $dom->getElementsByTagname('user')->item(0);
		if (is_null($node))
			return false;

		if (! $node->hasAttribute('login'))
			return false;

		return true;
	}

	public function getNetworkFoldersList() {
		if (! is_array($this->roles) || ! array_key_exists(Servers::$role_fs, $this->roles)) {
			Logger::critical('main', 'SERVER::getNetworkFoldersList - Not an FS');
			return false;
		}

		if (! $this->isOnline()) {
			Logger::debug('main', 'Server::getNetworkFoldersList server "'.$this->fqdn.':'.$this->web_port.'" is not online');
			return false;
		}

		$xml = query_url($this->getBaseURL().'/fs/shares');
		if (! $xml) {
			$this->isUnreachable();
			Logger::error('main', 'Server::getNetworkFoldersList server \''.$this->fqdn.'\' is unreachable');
			return false;
		}

		$dom = new DomDocument('1.0', 'utf-8');

		$buf = @$dom->loadXML($xml);
		if (! $buf)
			return false;

		if (! $dom->hasChildNodes())
			return false;

		$node = $dom->getElementsByTagname('shares')->item(0);
		if (is_null($node))
			return false;

		$share_nodes = $dom->getElementsByTagname('share');
		$shares = array();
		foreach ($share_nodes as $share_node) {
			if (is_null($share_node))
				return false;

			if (! $share_node->hasAttribute('id'))
				return false;

			$shares[] = $share_node->getAttribute('id');
		}

		return $shares;
	}

	public function updateNetworkFolders() {
		if (! is_array($this->roles) || ! array_key_exists(Servers::$role_fs, $this->roles)) {
			Logger::critical('main', 'Server::updateNetworkFolders - Not an FS');
			return false;
		}
		
		if (! $this->isOnline()) {
			Logger::debug('main', 'Server::updateNetworkFolders server "'.$this->fqdn.':'.$this->web_port.'" is not online');
			return false;
		}
		
		$forders_on_server = $this->getNetworkFoldersList();
		if (is_array($forders_on_server) === false) {
			Logger::error('main', 'Server::updateNetworkFolders getNetworkFoldersList failed for fqdn='.$this->fqdn);
			return false;
		}
		
		$folders_on_sm = Abstract_NetworkFolder::load_from_server($this->fqdn);
		if (is_array($folders_on_sm) === false) {
			Logger::error('main', 'Server::updateNetworkFolders Abstract_NetworkFolder::load_from_server failed for fqdn='.$this->fqdn);
			return false;
		}
		
		foreach ($forders_on_server as $folder_id) {
			$folder = Abstract_NetworkFolder::load($folder_id);
			if (is_object($folder) === false) {
				// networkfolder does not exist -> create it
				$folder = new NetworkFolder();
				$folder->id = $folder_id;
				$folder->name = $folder_id;
				$folder->server = $this->fqdn;
				Abstract_NetworkFolder::save($folder);
			}
		}
		
		return true;
	}
	
	public function createNetworkFolder($name_) {
		if (! is_array($this->roles) || ! array_key_exists(Servers::$role_fs, $this->roles)) {
			Logger::critical('main', 'SERVER::createNetworkFolder - Not an FS');
			return false;
		}

		if (! $this->isOnline()) {
			Logger::debug('main', 'Server::createNetworkFolder("'.$name_.'") server "'.$this->fqdn.':'.$this->web_port.'" is not online');
			return false;
		}

		$dom = new DomDocument('1.0', 'utf-8');

		$node = $dom->createElement('share');
		$node->setAttribute('id', $name_);
		$dom->appendChild($node);

		$xml = $dom->saveXML();

		$xml = query_url_post_xml($this->getBaseURL().'/fs/share/create', $xml);
		if (! $xml) {
			$this->isUnreachable();
			Logger::error('main', 'Server::createNetworkFolder server \''.$this->fqdn.'\' is unreachable');
			return false;
		}

		$dom = new DomDocument('1.0', 'utf-8');

		$buf = @$dom->loadXML($xml);
		if (! $buf)
			return false;

		if (! $dom->hasChildNodes())
			return false;

		$node = $dom->getElementsByTagname('share')->item(0);
		if (is_null($node))
			return false;

		if (! $node->hasAttribute('id'))
			return false;

		return true;
	}

	public function deleteNetworkFolder($name_, $force_=false) {
		if (! is_array($this->roles) || ! array_key_exists(Servers::$role_fs, $this->roles)) {
			Logger::critical('main', 'SERVER::deleteNetworkFolder - Not an FS');
			return false;
		}

		if (! $this->isOnline()) {
			Logger::debug('main', 'Server::deleteNetworkFolder("'.$name_.'") server "'.$this->fqdn.':'.$this->web_port.'" is not online');
			return false;
		}

		$dom = new DomDocument('1.0', 'utf-8');

		$node = $dom->createElement('share');
		$node->setAttribute('id', $name_);
		$node->setAttribute('force', $force_);
		$dom->appendChild($node);

		$xml = $dom->saveXML();

		$xml = query_url_post_xml($this->getBaseURL().'/fs/share/delete', $xml);
		if (! $xml) {
			$this->isUnreachable();
			Logger::error('main', 'Server::deleteNetworkFolder server \''.$this->fqdn.'\' is unreachable');
			return false;
		}

		$dom = new DomDocument('1.0', 'utf-8');

		$buf = @$dom->loadXML($xml);
		if (! $buf)
			return false;

		if (! $dom->hasChildNodes())
			return false;

		$node = $dom->getElementsByTagname('share')->item(0);
		if (is_null($node))
			return false;

		if (! $node->hasAttribute('id'))
			return false;

		return true;
	}

	public function addUserToNetworkFolder($name_, $login_, $password_) {
		if (! is_array($this->roles) || ! array_key_exists(Servers::$role_fs, $this->roles)) {
			Logger::critical('main', 'SERVER::addUserToNetworkFolder - Not an FS');
			return false;
		}

		if (! $this->isOnline()) {
			Logger::debug('main', 'Server::addUserToNetworkFolder("'.$name_.'", "'.$login_.'") server "'.$this->fqdn.':'.$this->web_port.'" is not online');
			return false;
		}

		$dom = new DomDocument('1.0', 'utf-8');

		$node = $dom->createElement('share');
		$node->setAttribute('id', $name_);
		$user_node = $dom->createElement('user');
		$user_node->setAttribute('login', $login_);
		$user_node->setAttribute('password', $password_);
		$node->appendChild($user_node);
		$dom->appendChild($node);

		$xml = $dom->saveXML();

		$xml = query_url_post_xml($this->getBaseURL().'/fs/share/users/add', $xml);
		if (! $xml) {
			$this->isUnreachable();
			Logger::error('main', 'Server::addUserToNetworkFolder server \''.$this->fqdn.'\' is unreachable');
			return false;
		}

		$dom = new DomDocument('1.0', 'utf-8');

		$buf = @$dom->loadXML($xml);
		if (! $buf)
			return false;

		if (! $dom->hasChildNodes())
			return false;

		$node = $dom->getElementsByTagname('share')->item(0);
		if (is_null($node))
			return false;

		if (! $node->hasAttribute('id'))
			return false;

		return true;
	}

	public function delUserFromNetworkFolder($name_, $login_) {
		if (! is_array($this->roles) || ! array_key_exists(Servers::$role_fs, $this->roles)) {
			Logger::critical('main', 'SERVER::delUserFromNetworkFolder - Not an FS');
			return false;
		}

		if (! $this->isOnline()) {
			Logger::debug('main', 'Server::delUserFromNetworkFolder("'.$name_.'", "'.$login_.'") server "'.$this->fqdn.':'.$this->web_port.'" is not online');
			return false;
		}

		$dom = new DomDocument('1.0', 'utf-8');

		$node = $dom->createElement('share');
		$node->setAttribute('id', $name_);
		$user_node = $dom->createElement('user');
		$user_node->setAttribute('login', $login_);
		$node->appendChild($user_node);
		$dom->appendChild($node);

		$xml = $dom->saveXML();

		$xml = query_url_post_xml($this->getBaseURL().'/fs/share/users/del', $xml);
		if (! $xml) {
			$this->isUnreachable();
			Logger::error('main', 'Server::delUserFromNetworkFolder server \''.$this->fqdn.'\' is unreachable');
			return false;
		}

		$dom = new DomDocument('1.0', 'utf-8');

		$buf = @$dom->loadXML($xml);
		if (! $buf)
			return false;

		if (! $dom->hasChildNodes())
			return false;

		$node = $dom->getElementsByTagname('share')->item(0);
		if (is_null($node))
			return false;

		if (! $node->hasAttribute('id'))
			return false;

		return true;
	}

	public function getWindowsADDomain() {
		Logger::debug('main', 'Starting Server::getWindowsADDomain for server \''.$this->fqdn.'\'');

		if ($this->getAttribute('type') != 'windows') {
			Logger::error('main', 'Server::getWindowsADDomain - \''.$this->fqdn.'\' is NOT a windows server');
			return false;
		}

		$dom = new DomDocument('1.0', 'utf-8');

		$ret = query_url_post($this->getWebservicesBaseURL().'/domain', false);
		if (! $ret) {
			$this->isUnreachable();
			Logger::error('main', 'Server::getWindowsADDomain server \''.$this->fqdn.'\' is unreachable');
			return false;
		}

		$buf = @$dom->loadXML($ret);
		if (! $buf) {
			Logger::error('main', 'Server::getWindowsADDomain Unable to get a valid XML from windows domain');
			return false;
		}

		if (! $dom->hasChildNodes()) {
			Logger::error('main', 'Server::getWindowsADDomain Unable to get a valid XML from windows domain');
			return false;
		}

		$domain_node = $dom->getElementsByTagname('domain')->item(0);
		if (is_null($domain_node)) {
			$error_node = $dom->getElementsByTagname('error')->item(0);
			if (is_null($error_node)) {
				Logger::error('main', 'Server::getWindowsADDomain Unable to get a valid XML from windows domain');
				return false;
			} else  {
				$buf = $error_node->getAttribute('id');
				if ($buf == 'no_domain') {
					Logger::info('main', 'Server::getWindowsADDomain No domain for server \''.$this->fqdn.'\'');
					$this->windows_domain = NULL;
				}
			}
		} else
			$this->windows_domain = $domain_node->getAttribute('name');

		return true;
	}

	public function getApplicationIcon($id_, $real_id_) {
		$ret = query_url($this->getBaseURL().'/aps/application/icon/'.$real_id_);
		if (! $ret)
			return false;

		if (! $ret || $ret == '')
			return false;

		if (! check_folder(CACHE_DIR.'/image') || ! check_folder(CACHE_DIR.'/image/application'))
			return false;

		@file_put_contents(CACHE_DIR.'/image/application/'.$id_.'.png', $ret);

		return true;
	}

	// ? unclean?
	public function getApplications() {
		Logger::debug('main', 'SERVER::getApplications for server '.$this->fqdn);

		if (! is_array($this->roles) || ! array_key_exists(Servers::$role_aps, $this->roles)) {
			Logger::critical('main', 'SERVER::getApplications - Not an ApS');
			return NULL;
		}

		$applicationDB = ApplicationDB::getInstance();

		$ls = Abstract_Liaison::load('ApplicationServer', NULL, $this->fqdn);
		if (is_array($ls)) {
			$res = array();
			foreach ($ls as $l) {
				$a = $applicationDB->import($l->element);
				if (is_object($a))
					$res []= $a;
			}
			return $res;
		} else {
			Logger::error('main', 'SERVER::getApplications elements is not array');
			return NULL;
		}
	}

	public function updateApplications(){
		Logger::debug('main', 'Server::updateApplications');

		if (! is_array($this->roles) || ! array_key_exists(Servers::$role_aps, $this->roles)) {
			Logger::critical('main', 'SERVER::updateApplications - Not an ApS');
			return false;
		}

		if (! $this->isOnline()) {
			Logger::debug('main', 'Server::updateApplications server "'.$this->fqdn.':'.$this->web_port.'" is not online');
			return false;
		}

		$applicationDB = ApplicationDB::getInstance();

		$xml = query_url($this->getBaseURL().'/aps/applications');
		if (! $xml) {
			$this->isUnreachable();
			Logger::error('main', 'Server::updateApplications server \''.$this->fqdn.'\' is unreachable');
			return false;
		}

		if (! is_string($xml)) {
			Logger::error('main', 'Server::updateApplications invalid xml1');
			return false;
		}

		if (substr($xml, 0, 5) == 'ERROR') {
			$this->returnedError();
			Logger::error('main', 'Server::updateApplications invalid xml2');
			return false;
		}

		if ($xml == '') {
			Logger::error('main', 'Server::updateApplications invalid xml3');
			return false;
		}

		$dom = new DomDocument('1.0', 'utf-8');
		@$dom->loadXML($xml);
		$root = $dom->documentElement;

		// before adding application, we remove all previous applications
		$previous_liaison = Abstract_Liaison::load('ApplicationServer', NULL, $this->fqdn); // see end of function
		$current_liaison_key = array();

		$application_node = $dom->getElementsByTagName("application");
		foreach($application_node as $app_node){
			$app_name = '';
			$app_description = '';
			$app_path_exe = '';
			$app_path_args = NULL;
			$app_path_icon = NULL;
			$app_package = NULL;
			$app_desktopfile = NULL;
			$app_mimetypes = NULL;
			if ($app_node->hasAttribute("name"))
				$app_name = $app_node->getAttribute("name");
			if ($app_node->hasAttribute("description"))
				$app_description = $app_node->getAttribute("description");
			if ($app_node->hasAttribute("package"))
				$app_package = $app_node->getAttribute("package");
			if ($app_node->hasAttribute("desktopfile"))
				$app_desktopfile = $app_node->getAttribute("desktopfile");
			if ($app_node->hasAttribute("id"))
				$app_real_id = $app_node->getAttribute("id");

			$exe_node = $app_node->getElementsByTagName('executable')->item(0);
			if ($exe_node->hasAttribute("command")) {
				$command = $exe_node->getAttribute("command");
				$command = str_replace(array("%U","%u","%c","%i","%f","%m",'"'),"",$command);
				$app_path_exe = trim($command);
			}
			if ($exe_node->hasAttribute("icon"))
				$app_path_icon =  ($exe_node->getAttribute("icon"));
			if ($exe_node->hasAttribute("mimetypes"))
				$app_mimetypes = $exe_node->getAttribute("mimetypes");

			$a = new Application(NULL,$app_name,$app_description,$this->getAttribute('type'),$app_path_exe,$app_package,$app_path_icon,$app_mimetypes,true,$app_desktopfile);
			$a_search = $applicationDB->search($app_name,$app_description,$this->getAttribute('type'),$app_path_exe);
			if (is_object($a_search)){
				//already in DB
				// echo $app_name." already in DB\n";
				$a = $a_search;
			}
			else {
				// echo $app_name." NOT in DB\n";
				if ($applicationDB->isWriteable() == false){
					Logger::debug('main', 'Server::updateApplications applicationDB is not writeable');
				}
				else{
					if ($applicationDB->add($a) == false){
						//echo 'app '.$app_name." not insert<br>\n";
						return false;
					}
				}
			}
			if ($applicationDB->isWriteable() == true){
				if ($applicationDB->isOK($a) == true){
					// we add the app to the server
					if (!is_object(Abstract_Liaison::load('ApplicationServer', $a->getAttribute('id'),$this->fqdn))) {
						$ret = Abstract_Liaison::save('ApplicationServer', $a->getAttribute('id'),$this->fqdn);
						if ($ret === false) {
							Logger::error('main', 'Server::updateApplications failed to save application');
							return $ret;
						}
					}
					if (! file_exists($a->getIconPathRW())) {
						$this->getApplicationIcon($a->getAttribute('id'), $app_real_id);
					}
					$current_liaison_key[] = $a->getAttribute('id');
				}
				else{
					//echo "Application not ok<br>\n";
				}
			}
		}

		$previous_liaison_key = array_keys($previous_liaison);
		foreach ($previous_liaison_key as $key){
			if (in_array($key, $current_liaison_key) == false) {
				$a = $applicationDB->import($key);
				if ( is_null($a) || $a->getAttribute('static') == false)
					Abstract_Liaison::delete('ApplicationServer', $key, $this->fqdn);
			}
		}
		return true;
	}
	
	public function getInstallableApplications() {
		Logger::debug('main', 'Server::getInstallableApplications');
		return query_url($this->getWebservicesBaseURL().'/installable_applications.php', false);
	}
}
