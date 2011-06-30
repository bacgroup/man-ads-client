<?php
/**
 * Copyright (C) 2011 Ulteo SAS
 * http://www.ulteo.com
 * Author Jeremy DESVAGES <jeremy@ulteo.com> 2011
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
require_once(dirname(__FILE__).'/../../includes/core.inc.php');

class Abstract_VDI {
	public static function init($prefs_) {
		Logger::debug('main', 'Starting Abstract_VDI::init');

		$sql_conf = $prefs_->get('general', 'sql');
		$SQL = SQL::newInstance($sql_conf);

		$vdi_table_structure = array(
			'id'			=>	'varchar(64)',
			'type'			=>	'varchar(255)',
			'name'			=>	'varchar(255)',
			'server'		=>	'varchar(255)',
			'master_id'		=>	'varchar(64)',
			'used_by'		=>	'varchar(255)',
			'cpu_model'		=>	'varchar(255)',
			'cpu_nb_cores'	=>	'int(8)',
			'ram_total'		=>	'int(8)',
			'status'		=>	'varchar(32)',
			'ip'			=>	'varchar(15)'
		);

		$ret = $SQL->buildTable($sql_conf['prefix'].'vdi', $vdi_table_structure, array('id'));

		if (! $ret) {
			Logger::error('main', 'Unable to create MySQL table \''.$sql_conf['prefix'].'vdi\'');
			return false;
		}

		Logger::debug('main', 'MySQL table \''.$sql_conf['prefix'].'vdi\' created');

		return true;
	}

	public static function exists($id_) {
		Logger::debug('main', 'Starting Abstract_VDI::exists for \''.$id_.'\'');

		$SQL = SQL::getInstance();

		$SQL->DoQuery('SELECT 1 FROM @1 WHERE @2 = %3 LIMIT 1', $SQL->prefix.'vdi', 'id', $id_);
		$total = $SQL->NumRows();

		if ($total == 0)
			return false;

		return true;
	}

	public static function load($id_) {
		Logger::debug('main', 'Starting Abstract_VDI::load for \''.$id_.'\'');

		$SQL = SQL::getInstance();

		$SQL->DoQuery('SELECT * FROM @1 WHERE @2 = %3 LIMIT 1', $SQL->prefix.'vdi', 'id', $id_);
		$total = $SQL->NumRows();

		if ($total == 0) {
			Logger::error('main', "Abstract_VDI::load($id_) failed: NumRows == 0");
			return false;
		}

		$row = $SQL->FetchResult();

		$buf = self::generateFromRow($row);

		return $buf;
	}

	public static function save($machine_) {
		Logger::debug('main', 'Starting Abstract_VDI::save for \''.$machine_->id.'\'');

		$SQL = SQL::getInstance();

		$id = $machine_->id;

		if (! Abstract_VDI::exists($machine_->id)) {
			Logger::debug('main', "Abstract_VDI::save($machine_) virtual machine does NOT exist, we must create it");

			if (! Abstract_VDI::create($machine_)) {
				Logger::error('main', "Abstract_VDI::save($machine_) create failed");
				return false;
			}
		}

		$SQL->DoQuery('UPDATE @1 SET @4=%5,@6=%7,@8=%9,@10=%11,@12=%13,@14=%15,@16=%17,@18=%19,@20=%21,@22=%23 WHERE @2 = %3 LIMIT 1', $SQL->prefix.'vdi', 'id', $id, 'type', $machine_->type, 'name', $machine_->name, 'server', $machine_->server, 'master_id', $machine_->master_id, 'used_by', $machine_->used_by, 'cpu_model', $machine_->cpu_model, 'cpu_nb_cores', $machine_->cpu_nb_cores, 'ram_total', $machine_->ram_total, 'status', $machine_->status, 'ip', $machine_->ip);

		return true;
	}

	private static function create($machine_) {
		Logger::debug('main', 'Starting Abstract_VDI::create for \''.$machine_->id.'\'');

		if (Abstract_VDI::exists($machine_->id)) {
			Logger::error('main', 'Abstract_VDI::create(\''.$machine_->id.'\') virtual machine already exists');
			return false;
		}

		$SQL = SQL::getInstance();

		$SQL->DoQuery('INSERT INTO @1 (@2) VALUES (%3)', $SQL->prefix.'vdi', 'id', $machine_->id);

		return true;
	}

	public static function delete($id_) {
		Logger::debug('main', 'Starting Abstract_VDI::delete for \''.$id_.'\'');

		$SQL = SQL::getInstance();

		if (! Abstract_VDI::exists($id_)) {
			Logger::error('main', 'Abstract_VDI::delete(\''.$id_.'\') virtual machine does not exist');
			return false;
		}

		$SQL->DoQuery('DELETE FROM @1 WHERE @2 = %3 LIMIT 1', $SQL->prefix.'vdi', 'id', $id_);

		return true;
	}

	private static function generateFromRow($row_) {
		foreach ($row_ as $k => $v)
			$$k = $v;

		switch ($row_['type']) {
			case 'master_os':
				$classname = 'VDI_MasterOS';
				break;
			case 'vm':
				$classname = 'VDI_VM';
				break;
			default:
				$classname = 'VDI';
				break;
		}

		$buf = new $classname((string)$id);
		$buf->type = (string)$type;
		$buf->name = (string)$name;
		$buf->server = (string)$server;
		$buf->master_id = (string)$master_id;
		$buf->used_by = (string)$used_by;
		$buf->cpu_model = (string)$cpu_model;
		$buf->cpu_nb_cores = (int)$cpu_nb_cores;
		$buf->ram_total = (int)$ram_total;
		$buf->status = (string)$status;
		$buf->ip = (string)$ip;

		$buf->init();

		return $buf;
	}

	public static function loadByServer($fqdn_) {
		Logger::debug('main', 'Starting Abstract_VDI::load_all');

		$SQL = SQL::getInstance();

		$SQL->DoQuery('SELECT * FROM @1 WHERE @2 = %3', $SQL->prefix.'vdi', 'server', $fqdn_);
		$rows = $SQL->FetchAllResults();

		$machines = array();
		foreach ($rows as $row) {
			$machine = self::generateFromRow($row);
			if (! is_object($machine))
				continue;

			$machines[] = $machine;
		}

		return $machines;
	}

	public static function loadByUser($login_) {
		Logger::debug('main', 'Starting Abstract_VDI::load_all');

		$SQL = SQL::getInstance();

		$SQL->DoQuery('SELECT * FROM @1 WHERE @2 = %3', $SQL->prefix.'vdi', 'used_by', $login_);
		$rows = $SQL->FetchAllResults();

		$machines = array();
		foreach ($rows as $row) {
			$machine = self::generateFromRow($row);
			if (! is_object($machine))
				continue;

			$machines[] = $machine;
		}

		return $machines;
	}
}
