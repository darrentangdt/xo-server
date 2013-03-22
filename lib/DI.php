<?php
/**
 * This file is a part of Xen Orchestra Server.
 *
 * Xen Orchestra Server is free software: you can redistribute it
 * and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation, either version 3 of
 * the License, or (at your option) any later version.
 *
 * Xen Orchestra Server is distributed in the hope that it will be
 * useful, but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Xen Orchestra Server. If not, see
 * <http://www.gnu.org/licenses/>.
 *
 * @author Julien Fontanet <julien.fontanet@vates.fr>
 * @license http://www.gnu.org/licenses/gpl-3.0-standalone.html GPLv3
 *
 * @package Xen Orchestra Server
 */

/**
 * Dependency injector.
 */
final class DI extends Base
{
	function __construct()
	{
		parent::__construct();
	}

	function get($id)
	{
		if (isset($this->_entries[$id])
		    || array_key_exists($id, $this->_entries))
		{
			return $this->_entries[$id];
		}

		$tmp = str_replace(array('_', '.'), array('', '_'), $id);

		if (method_exists($this, '_get_'.$tmp))
		{
			return $this->{'_get_'.$tmp}();
		}

		if (method_exists($this, '_init_'.$tmp))
		{
			$value = $this->{'_init_'.$tmp}();
			$this->set($id, $value);
			return $value;
		}

		throw new Exception('no such entry: '.$id);
	}

	function set($id, $value)
	{
		$this->_entries[$id] = $value;
	}

	private $_entries = array();

	////////////////////////////////////////

	private function _init_application()
	{
		return new Application($this);
	}

	private function _init_database()
	{
		$config = $this->get('config');

		$type = $config['database.type'];
		if ('json' !== $type)
		{
			trigger_error(
				'unsupported database type ('.$type.')',
				E_USER_ERROR
			);
		}

		$file = $config['database.file'];
		if (file_exists($file))
		{
			$data = @file_get_contents($file);
			if ((false === $data)
				|| (null === ($data = json_decode($data, true))))
			{
				trigger_error(
					'could not read the database',
					E_USER_ERROR
				);
			}

			return \Rekodi\Manager\Memory::createFromState($data);
		}

		$manager = new \Rekodi\Manager\Memory;

		// Create tables.
		$manager->createTable('tokens', function ($table) {
			$table
				->string('id')->unique()
				->integer('expiration')
				->string('user_id')
			;
		});
		$manager->createTable('users', function ($table) {
			$table
				->integer('id')->autoIncremented()
				->string('name')->unique()
				->string('password')
				->integer('permission')
			;
		});

		// Insert initial data.
		$manager->create('users', array(
			array(
				'name'       => 'admin',
				'password'   => '$2y$10$VzBQqiwnhG5zc2.MQmmW4ORcPW6FE7SLhPr1VBV2ubn5zJoesnmli',
				'permission' => \Bean\User::ADMIN,
			),
		));

		trigger_error(
			'no existing database, creating default user (admin:admin)',
			E_USER_WARNING
		);

		return $manager;
	}

	private function _init_errorLogger()
	{
		return new ErrorLogger($this->get('logger'));
	}

	private function _init_logger()
	{
		$logger = new \Monolog\Logger('main');

		$config = $this->get('config');
		if ($email = $config->get('log.email', false))
		{
			$logger->pushHandler(
				new \Monolog\Handler\FingersCrossedHandler(
					new \Monolog\Handler\NativeMailerHandler(
						$email,
						'[XO Server]',
						'no-reply@vates.fr',
						\Monolog\Logger::DEBUG
					),
					\Monolog\Logger::WARNING
				)
			);
		}
		if ($file = $config->get('log.file', false))
		{
			$logger->pushHandler(
				new \Monolog\Handler\StreamHandler($file)
			);
		}

		return $logger;
	}

	private function _init_loop()
	{
		return new Loop;
	}

	private function _init_tokens()
	{
		return new \Manager\Tokens(
			$this->get('database')
		);
	}

	private function _init_users()
	{
		return new \Manager\Users(
			$this->get('database')
		);
	}

	private function _init_vms()
	{
		$database = new \Rekodi\Manager\Memory;
		$database->createTable('vms', function ($table) {
			$table
				->string('id')->unique()
			;
		});

		return new \Manager\VMs($database);
	}
}