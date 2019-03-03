<?php
/**
 * @author Piotr Mrowczynski piotr@owncloud.com
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

namespace OCA\DAV\DAV;

use OCA\DAV\Connector\Sabre\Exception\Forbidden;
use OCA\DAV\Connector\Sabre\File;
use OCP\Files\Folder;
use OCP\Files\InvalidPathException;
use Sabre\DAV\Exception\ServiceUnavailable;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;
use Sabre\HTTP\RequestInterface;
use Sabre\DAV\Exception\NotFound;
use \OCP\Files\NotFoundException;

/**
 * Sabre plugin for the the file secure-view:
 */
class ViewOnlyPlugin extends ServerPlugin {

	/** @var \Sabre\DAV\Server $server */
	private $server;

	/**
	 * ViewOnlyPlugin plugin
	 */
	public function __construct() {
	}

	/**
	 * This initializes the plugin.
	 *
	 * This function is called by Sabre\DAV\Server, after
	 * addPlugin is called.
	 *
	 * This method should set up the required event subscriptions.
	 *
	 * @param Server $server
	 * @return void
	 */
	public function initialize(Server $server) {
		$this->server = $server;
		//priority 90 to make sure the plugin is called before
		//Sabre\DAV\CorePlugin::httpGet
		$this->server->on('method:GET', [$this, 'checkViewOnly'], 90);
	}

	/**
	 *
	 * @param RequestInterface $request request object
	 * @return boolean
	 * @throws Forbidden
	 * @throws ServiceUnavailable
	 */
	public function checkViewOnly(
		RequestInterface $request
	) {
		$path = $request->getPath();

		try {
			$davNode = $this->server->tree->getNodeForPath($path);
			if (!($davNode instanceof File || $davNode instanceof Folder)) {
				return true;
			}

			// Restrict view-only to nodes which are shared
			$node = $davNode->getNode();
			$storage = $node->getStorage();
			if (!$storage->instanceOfStorage('\OCA\Files_Sharing\SharedStorage')) {
				return true;
			}

			// Extract extra permissions
			/** @var \OCA\Files_Sharing\SharedStorage $storage */
			$share = $storage->getShare();

			// Check if read-only and on whether permission can download is both set and disabled.
			$canDownload = $share->getExtraPermissions()->getPermission('dav', 'can-download');
			if (!$node->isUpdateable() && $canDownload !== null && !$canDownload) {
				throw new Forbidden('File is in secure-view mode and cannot be directly downloaded.');
			}
		} catch (NotFound $e) {
		} catch (NotFoundException $e) {
		} catch (InvalidPathException $e) {
		}

		return true;
	}
}