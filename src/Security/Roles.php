<?php

declare(strict_types=1);

namespace S3Files\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * A place for all roles to be defined so we don't have strings everywhere.
 *
 * VoterInterface is implemented for IDE completion since strings must be used in Twig templates.
 */
final class Roles
    // Only for IDE completion - see vote().
    implements VoterInterface
{
    // Roles used in system
    public const VIEW_FILES = 'ROLE_VIEW_FILES';
    public const UPLOAD_FILES = 'ROLE_UPLOAD_FILES';

    // These are auto assigned from the user's groups. Obv we have more than defined here. Feel free to add as needed.
    // These shouldn't be referenced when checking for authorization, but should be mapped to the roles defined above
    // in `security.role_hierarchy`. This keeps application features decoupled from the groups that have access to them.
    private const GROUP_EVERYONE = 'ROLE_GROUP_EVERYONE';

    /**
     * Get the role hierarchy used to couple the auto assigned user groups to roles used throughout this system.
     *
     * @return array
     */
    public static function getHierarchy(): array
    {
        return [
            Roles::GROUP_EVERYONE => [
                Roles::VIEW_FILES,
                Roles::UPLOAD_FILES,
            ],
        ];
    }

    // Only written here so IDE Symfony Plugin auto completes these roles
    public function vote(TokenInterface $token, $subject, array $attributes)
    {
        foreach ($attributes as $attribute) {
            in_array($attribute, [

                self::VIEW_FILES,
            ]);
        }
    }

    /**
     * Private constructor.
     */
    private function __construct()
    {
    }
}
