<?php
/**
 * @copyright Copyright (c) 2020 - 2021 Bastien Cecchinato <bcecchinato@users.noreply.github.com>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\UserUnixScript\AppInfo;

use OCA\UserUnixScript\Backend\UserBackend;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\IUserManager;

class Application extends App implements IBootstrap {

    const APP_NAME = 'user_unix_script';

    public function __construct() {
        parent::__construct(self::APP_NAME);
    }

    public function register(IRegistrationContext $context): void {
    }

    public function boot(IBootContext $context): void {
        $userBackend = $context->getAppContainer()->query(UserBackend::class);
        $userManager = $context->getAppContainer()->query(IUserManager::class);
        $userManager->registerBackend($userBackend);
    }
}
