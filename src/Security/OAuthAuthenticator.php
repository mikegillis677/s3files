<?php

declare(strict_types=1);

namespace S3Files\Security;

use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use League\OAuth2\Client\Provider;

/**
 * A Guard Authenticator for League's OAuth library.
 */
class OAuthAuthenticator extends AbstractGuardAuthenticator
{
    use TargetPathTrait;

    /** @var Provider\Google */
    private $provider;
    /** @var HttpUtils */
    private $httpUtils;
    /** @var string[] */
    private $scopes;
    /** @var string */
    private $redirectPath;
    /** @var string */
    private $environment;

    /**
     * Constructor.
     *
     * @param Provider\Google $provider
     * @param HttpUtils $httpUtils
     * @param string $environment
     */
    public function __construct(Provider\Google $provider, HttpUtils $httpUtils, string $environment)
    {
        $this->provider = $provider;
        $this->httpUtils = $httpUtils;

        $this->scopes = [];
        $this->redirectPath = '/';
        $this->environment = $environment;
    }

    public function supports(Request $request)
    {
        return (bool) $request->query->get('code');
    }

    public function getCredentials(Request $request)
    {
        if ($error = $request->query->get('error')) {
            throw new AuthenticationException($error);
        }

        if ($session = $request->getSession()) {
            $actualState = $request->query->get('state');
            if (!$actualState || $actualState !== $session->get('oauth2state')) {
                $session->remove('oauth2state');
            }
        }

        try {
            $token = $this->provider->getAccessToken('authorization_code', [
                'code' => $request->query->get('code'),
                'redirect_uri' => $this->getRedirectUri($request),
            ]);
        } catch (IdentityProviderException $e) {
            throw new AuthenticationException($e->getMessage(), 0, $e);
        }

        return $token;
    }

    public function getUser($token, UserProviderInterface $userProvider): ?UserInterface
    {
        $owner = $this->provider->getResourceOwner($token);

        return $userProvider->loadUserByUsername($owner->getId());
    }

    public function checkCredentials($credentials, UserInterface $user): bool
    {
        return true; // Already validated
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey): ?Response
    {
        $targetPath = '/';
        if ($session = $request->getSession()) {

            $targetPath = $this->getTargetPath($session, $providerKey);
            $this->removeTargetPath($session, $providerKey);
        }

        if (empty($targetPath)) {
            $targetPath = '/';
        }

        return new RedirectResponse($targetPath);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return null; // go to start
    }

    public function start(Request $request, AuthenticationException $authException = null): Response
    {
        $originalProtocol = $request->headers->get('x-forwarded-proto') ?: $request->server->get('REQUEST_SCHEME');
        if ($this->environment === 'prod' && $originalProtocol === 'http') {
            return new RedirectResponse(str_replace('http://', 'https://', $this->httpUtils->generateUri($request, 'homepage')));
        }

        $url = $this->provider->getAuthorizationUrl([
            'redirect_uri' => $this->getRedirectUri($request),
            'scope'        => $this->scopes,
        ]);

        if ($session = $request->getSession()) {
            $session->set('oauth2state', $this->provider->getState());
        }

        return new RedirectResponse($url);
    }

    public function supportsRememberMe(): bool
    {
        return true;
    }

    private function getRedirectUri(Request $request): string
    {
        $uri = $this->httpUtils->generateUri($request, $this->redirectPath);
        if ($this->environment === 'prod') {
            $uri = str_replace('http://', 'https://', $uri);
        }
        return $uri;
    }
}
