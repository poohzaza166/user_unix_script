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

namespace OCA\UserUnixScript\Backend;

use OC\User\Backend;
use OCP\IConfig;
use OCP\ILogger;
use OCP\ISession;
use OCP\IUserBackend;
use OCP\UserInterface;

class UserBackend implements IUserBackend, UserInterface {

    const APP_NAME = 'user_unix_script';
    const LOG_CONTEXT = ['app' => self::APP_NAME];

    private $config;
    private $logger;
    private $users;

    public function __construct(IConfig $config, ILogger $logger, ISession $session) {
        $this->config = $config;
        $this->logger = $logger;
        $this->users = [];

        $group = posix_getgrnam(trim($this->config->getAppValue(self::APP_NAME, 'group', 'users')));
        $uid = intval(trim($this->config->getAppValue(self::APP_NAME, 'uid', '1000')));

        if ($session->exists(self::APP_NAME)) {
            $this->users = unserialize($this->session->get(self::APP_NAME));
        }

        if (is_array($this->users) && !empty($this->users)) {
            return;
        } else if (!is_array($group) || empty($group)) {
            return;
        }

        foreach ($group["members"] as $member) {
            $memberInfo = posix_getpwnam($member);
            if (is_array($memberInfo) && $memberInfo["uid"] >= $uid) {
                $this->users[$memberInfo["name"]] = $memberInfo["gecos"];
            }
        }

        $session->set(self::APP_NAME, serialize($this->users));
    }

    public function getBackendName(): string {
        return 'Unix Script';
    }

    public function implementsActions($actions): bool {
        $implements = Backend::GET_DISPLAYNAME | Backend::COUNT_USERS;
        $implements |= !empty(trim($this->config->getAppValue(self::APP_NAME, 'userCreate'))) ? Backend::CREATE_USER : 0;
        $implements |= !empty(trim($this->config->getAppValue(self::APP_NAME, 'userLogin'))) ? Backend::CHECK_PASSWORD : 0;
        $implements |= !empty(trim($this->config->getAppValue(self::APP_NAME, 'userUpdate'))) ? Backend::SET_PASSWORD : 0;

        return (bool)($actions & $implements);
    }

    public function getUsers($search = '', $limit = 10, $offset = 10): array {
        $filter = function ($key, $value) use (&$search) {
            return empty(trim($search)) || stripos($search, $key) !== false || stripos($search, $value) !== false;
        };

        return array_keys(array_slice(array_filter($this->users, $filter, ARRAY_FILTER_USE_BOTH), $offset, $limit > 0 ? $limit : null));
    }

    public function userExists($uid): bool {
        return array_key_exists($uid, $this->users);
    }

    public function getDisplayName($uid): string {
        if (!$this->userExists($uid)) {
            return false;
        }

        return $this->users[$uid];
    }

    public function getDisplayNames($search = '', $limit = 10, $offset = 10): array {
        $filter = function ($key, $value) use (&$search) {
            return empty(trim($search)) || stripos($search, $key) !== false || stripos($search, $value) !== false;
        };

        return array_slice(array_filter($this->users, $filter, ARRAY_FILTER_USE_BOTH), $offset, $limit > 0 ? $limit : null);
    }

    public function countUsers(): int {
        return count(array_keys($this->users), COUNT_NORMAL);
    }

    public function hasUserListings(): bool {
        return true;
    }

    public function createUser($uid, $password): bool {
        return $this->executeCommand('userCreate', $uid, $password);
    }

    public function checkPassword($uid, $password): string {
        if ($this->executeCommand('userLogin', $uid, $password)) {
            return $uid;
        } else {
            return false;
        }
    }

    public function setPassword($uid, $password): bool {
        return $this->executeCommand('userUpdate', $uid, $password);
    }

    public function deleteUser($uid): bool {
        return $this->executeCommand('userDelete', $uid, '');
    }

    private function executeCommand($action, $uid, $password): bool {
        $command = trim(str_replace('%u', $uid, str_replace('%p', $password, $this->config->getAppValue(self::APP_NAME, $action))));
        if (empty($command)) {
            $this->logger->error('You need to configure the script to run the action: ' . $action, self::LOG_CONTEXT);
            return false;
        }

        $result_value = null;
        $result_code = null;

        exec(escapeshellcmd($command), $result_value, $result_code);
        return !is_null($result_code) && $result_code === 0;
    }
}
