<?php

declare(strict_types=1);

namespace S3Files\Security;

use Symfony\Component\Security\Core\User\UserInterface;

class GoogleUser implements UserInterface
{
    /** @var string */
    private $id;
    /** @var string */
    private $email;
    /** @var string */
    private $name;
    /** @var string[] */
    private $roles;

    /**
     * Constructor.
     *
     * @param string            $id
     * @param string            $email
     * @param string            $name
     * @param string[] $roles
     */
    public function __construct(string $id, string $email, string $name, iterable $roles = [])
    {
        $this->id = $id;
        $this->email = $email;
        $this->name = $name;
        $this->roles = $roles;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getUsername(): string
    {
        return $this->getId();
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getPassword(): ?string
    {
        return null;
    }

    public function getSalt(): ?string
    {
        return null;
    }

    public function eraseCredentials(): void
    {
    }
}
