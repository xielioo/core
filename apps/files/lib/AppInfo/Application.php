<?php
/**
 * @author Christoph Wurst <christoph@owncloud.com>
 * @author Roeland Jago Douma <rullzer@owncloud.com>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Tobias Kaminsky <tobias@kaminsky.me>
 * @author Vincent Petry <pvince81@owncloud.com>
 *
 * @copyright Copyright (c) 2018, ownCloud GmbH
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */
namespace OCA\Files\AppInfo;

use OCA\Files\Controller\ApiController;
use OCA\Files\Controller\ViewController;
use OCA\Files\Service\TagService;
use OCA\Files\ViewOnly;
use OCP\AppFramework\App;
use OCP\IContainer;
use Symfony\Component\EventDispatcher\GenericEvent;

class Application extends App {
	public function __construct(array $urlParams= []) {
		parent::__construct('files', $urlParams);
		$container = $this->getContainer();
		$server = $container->getServer();

		/**
		 * Controllers
		 */
		$container->registerService('APIController', function (IContainer $c) use ($server) {
			return new ApiController(
				$c->query('AppName'),
				$c->query('Request'),
				$server->getUserSession(),
				$c->query('TagService'),
				$server->getPreviewManager(),
				$server->getShareManager(),
				$server->getConfig()
			);
		});

		$container->registerService('ViewController', function (IContainer $c) use ($server) {
			return new ViewController(
				$c->query('AppName'),
				$c->query('Request'),
				$server->getURLGenerator(),
				$c->query('L10N'),
				$server->getConfig(),
				$server->getEventDispatcher(),
				$server->getUserSession(),
				$server->getAppManager(),
				$server->getRootFolder()
			);
		});

		/**
		 * Core
		 */
		$container->registerService('L10N', function (IContainer $c) {
			return $c->query('ServerContainer')->getL10N($c->query('AppName'));
		});

		/**
		 * Services
		 */
		$container->registerService('TagService', function (IContainer $c) {
			return new TagService(
				$c->query('ServerContainer')->getUserSession(),
				$c->query('ServerContainer')->getTagManager(),
				$c->query('ServerContainer')->getLazyRootFolder()
			);
		});

		$container->registerService('OCP\Lock\ILockingProvider', function (IContainer $c) {
			return $c->query('ServerContainer')->getLockingProvider();
		});
		$container->registerService('OCP\Files\IMimeTypeLoader', function (IContainer $c) {
			return $c->query('ServerContainer')->getMimeTypeLoader();
		});

		/*
		 * Register capabilities
		 */
		$container->registerCapability('OCA\Files\Capabilities');

		$dispatcher = $this->getContainer()->getServer()->getEventDispatcher();

		// Make sure all legacy internal api calls are checked
		$viewOnlyHandler = new ViewOnly();
		$dispatcher->addListener('file.beforeCreateZip', function (GenericEvent $event) use ($viewOnlyHandler) {
			$dir = $event->getArgument('dir');
			$files = $event->getArgument('files');

			$pathsToCheck = [];
			if (\is_array($files)) {
				foreach ($files as $file) {
					$pathsToCheck[] = $dir . '/' . $file;
				}
			} elseif (\is_string($files)) {
				$pathsToCheck[] = $dir . '/' . $files;
			}

			$event->setArgument(
				'run',
				$viewOnlyHandler->check($pathsToCheck)
			);
		});

		$dispatcher->addListener('file.beforeGetDirect', function (GenericEvent $event) use ($viewOnlyHandler) {
			$pathsToCheck[] = $event->getArgument('path');

			$event->setArgument(
				'run',
				$viewOnlyHandler->check($pathsToCheck)
			);
		});
	}
}
