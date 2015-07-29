<?php
/* Icinga Web 2 | (c) 2013-2015 Icinga Development Team | GPLv2+ */

namespace Icinga\Authentication;

use Exception;
use Icinga\Application\Config;
use Icinga\Application\Icinga;
use Icinga\Application\Logger;
use Icinga\Authentication\UserGroup\UserGroupBackend;
use Icinga\Data\ConfigObject;
use Icinga\Exception\IcingaException;
use Icinga\Exception\NotReadableError;
use Icinga\User;
use Icinga\User\Preferences;
use Icinga\User\Preferences\PreferencesStore;
use Icinga\Web\Session;

class Auth
{
    /**
     * Singleton instance
     *
     * @var self
     */
    private static $instance;

    /**
     * Request
     *
     * @var \Icinga\Web\Request
     */
    protected $request;

    /**
     * Authenticated user
     *
     * @var User
     */
    private $user;


    /**
     * @see getInstance()
     */
    private function __construct()
    {
    }

    /**
     * Get the authentication manager
     *
     * @return self
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get the auth chain
     *
     * @return AuthChain
     */
    public function getAuthChain()
    {
        return new AuthChain();
    }

    /**
     * Whether the user is authenticated
     *
     * @param  bool $ignoreSession True to prevent session authentication
     *
     * @return bool
     */
    public function isAuthenticated($ignoreSession = false)
    {
        if ($this->user === null && ! $ignoreSession) {
            $this->authenticateFromSession();
        }
        return is_object($this->user);
    }

    public function setAuthenticated(User $user, $persist = true)
    {
        $username = $user->getUsername();
        try {
            $config = Config::app();
        } catch (NotReadableError $e) {
            Logger::error(
                new IcingaException(
                    'Cannot load preferences for user "%s". An exception was thrown: %s',
                    $username,
                    $e
                )
            );
            $config = new Config();
        }
        if ($config->get('global', 'config_backend', 'ini') !== 'none') {
            $preferencesConfig = new ConfigObject(array(
                'store'     => $config->get('global', 'config_backend', 'ini'),
                'resource'  => $config->get('global', 'config_resource')
            ));
            try {
                $preferencesStore = PreferencesStore::create(
                    $preferencesConfig,
                    $user
                );
                $preferences = new Preferences($preferencesStore->load());
            } catch (Exception $e) {
                Logger::error(
                    new IcingaException(
                        'Cannot load preferences for user "%s". An exception was thrown: %s',
                        $username,
                        $e
                    )
                );
                $preferences = new Preferences();
            }
        } else {
            $preferences = new Preferences();
        }
        $user->setPreferences($preferences);
        $groups = $user->getGroups();
        foreach (Config::app('groups') as $name => $config) {
            try {
                $groupBackend = UserGroupBackend::create($name, $config);
                $groupsFromBackend = $groupBackend->getMemberships($user);
            } catch (Exception $e) {
                Logger::error(
                    'Can\'t get group memberships for user \'%s\' from backend \'%s\'. An exception was thrown: %s',
                    $username,
                    $name,
                    $e
                );
                continue;
            }
            if (empty($groupsFromBackend)) {
                continue;
            }
            $groupsFromBackend = array_values($groupsFromBackend);
            $groups = array_merge($groups, array_combine($groupsFromBackend, $groupsFromBackend));
        }
        $user->setGroups($groups);
        $admissionLoader = new AdmissionLoader();
        list($permissions, $restrictions) = $admissionLoader->getPermissionsAndRestrictions($user);
        $user->setPermissions($permissions);
        $user->setRestrictions($restrictions);
        $this->user = $user;
        if ($persist) {
            $this->persistCurrentUser();
        }
    }

    /**
     * Getter for groups belonged to authenticated user
     *
     * @return  array
     * @see     User::getGroups
     */
    public function getGroups()
    {
        return $this->user->getGroups();
    }

    /**
     * Get the request
     *
     * @return \Icinga\Web\Request
     */
    public function getRequest()
    {
        if ($this->request === null) {
            $this->request = Icinga::app()->getFrontController()->getRequest();
        }
        return $this->request;
    }

    /**
     * Get applied restrictions matching a given restriction name
     *
     * Returns a list of applied restrictions, empty if no user is
     * authenticated
     *
     * @param  string  $restriction  Restriction name
     * @return array
     */
    public function getRestrictions($restriction)
    {
        if (! $this->isAuthenticated()) {
            return array();
        }
        return $this->user->getRestrictions($restriction);
    }

    /**
     * Returns the current user or null if no user is authenticated
     *
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Try to authenticate the user with the current session
     *
     * Authentication for externally-authenticated users will be revoked if the username changed or external
     * authentication is no longer in effect
     */
    public function authenticateFromSession()
    {
        $this->user = Session::getSession()->get('user');
        if ($this->user !== null && $this->user->isExternalUser() === true) {
            list($originUsername, $field) = $this->user->getExternalUserInformation();
            if (! array_key_exists($field, $_SERVER) || $_SERVER[$field] !== $originUsername) {
                $this->removeAuthorization();
            }
        }
    }

    /**
     * Whether an authenticated user has a given permission
     *
     * @param  string  $permission  Permission name
     *
     * @return bool                 True if the user owns the given permission, false if not or if not authenticated
     */
    public function hasPermission($permission)
    {
        if (! $this->isAuthenticated()) {
            return false;
        }
        return $this->user->can($permission);
    }

    /**
     * Writes the current user to the session
     */
    public function persistCurrentUser()
    {
        Session::getSession()->set('user', $this->user)->refreshId();
    }

    /**
     * Purges the current authorization information and session
     */
    public function removeAuthorization()
    {
        $this->user = null;
        Session::getSession()->purge();
    }
}
