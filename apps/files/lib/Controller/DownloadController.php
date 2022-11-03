<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022 Louis Chmn <louis@chmn.me>
 *
 * @author Louis Chmn <louis@chmn.me>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Files\Controller;

use OC\AppFramework\Bootstrap\Coordinator;
use OC\Files\Node\Node;
use OCP\AppFramework\Http\ZipResponse;
use OCP\AppFramework\Controller;
use OCP\Files\File;
use OCP\Files\IFileDownloadProvider;
use OCP\IRequest;

class DownloadController extends Controller {
	private Coordinator $coordinator;

	public function __construct(
		string $appName,
		IRequest $request,
		Coordinator $coordinator,
	) {
		parent::__construct($appName, $request);

		$this->request = $request;
		$this->coordinator = $coordinator;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function index(string $files): ZipResponse {
		$files = json_decode($files);
		$context = $this->coordinator->getRegistrationContext();
		$providerRegistrations = $context->getFileDownloadProviders();

		/** @var array<string, ?Node> */
		$nodes = [];

		foreach ($files as $filePath) {
			foreach ($providerRegistrations as $registration) {
				try {
					/** @var IFileDownloadProvider */
					$provider = \OCP\Server::get($registration->getService());

					$node = $provider->getNode($filePath);
					if ($node !== null) {
						$nodes[$filePath] = $node;
						break;
					}
				} catch (\Exception $ex) {
					throw $ex;
					// TODO: Log warning.
				}
			}
		}

		$response = new ZipResponse($this->request, 'download');

		foreach ($nodes as $filePath => $node) {
			// TODO: handle folders?
			if ($node instanceof File) {
				// TODO: maybe trim common prefix among files.
				$response->addResource($node->fopen('r'), $filePath, $node->getSize());
			}
		}

		return $response;
	}
}
