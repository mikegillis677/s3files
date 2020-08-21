<?php

declare(strict_types=1);

namespace S3Files\Security;

use Google_Service_Directory as Directory;
use Google_Service_Directory_Group as Group;
use Google_Service_Directory_User as User;
use Google_Service_Exception as ServiceException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class GoogleUserProvider implements UserProviderInterface
{
    /** @var Directory */
    private $directory;
    /** @var bool */
    private $includeAliases = true;

    /**
     * Constructor.
     *
     * @param Directory $directory
     */
    public function __construct(Directory $directory)
    {
        $this->directory = $directory;
    }

    /**
     * {@inheritdoc}
     */
    public function loadUserByUsername($username): UserInterface
    {
        try {
            $user = $this->getUser($username);
        } catch (ServiceException $e) {
            throw $e->getCode() === 404 ? new UsernameNotFoundException() : $e;
        }

        $roles = array_map(function (string $email) {
            [$group] = explode('@', $email, 2);

            return 'ROLE_GROUP_' . strtoupper($group);
        }, $this->getGroups($username));

        return new GoogleUser($username, $user->getPrimaryEmail(), $user->getName()->getFullName(), $roles);
    }

    /**
     * {@inheritdoc}
     */
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof GoogleUser) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_class($user)));
        }

        return $user;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsClass($class): bool
    {
        return $class instanceof GoogleUser;
    }

    private function getUser(string $userId): User
    {
        return $this->directory->users->get($userId, [
            'viewType' => 'domain_public',
        ]);
    }

    /**
     * @param string $userId
     *
     * @return string[] list of emails
     */
    private function getGroups(string $userId): array
    {
        /** @var Group[] $groups */
        $groups = $this->directory->groups->listGroups([
            'userKey' => $userId,
        ]);

        $emails = [];
        foreach ($groups as $group) {
            $emails[] = $group->getEmail();
            if ($this->includeAliases && $group->getAliases()) {
                $emails = array_merge($emails, $group->getAliases());
            }
        }

        return $emails;
    }
}
