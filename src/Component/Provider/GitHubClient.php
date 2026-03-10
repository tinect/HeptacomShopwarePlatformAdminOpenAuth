<?php

declare(strict_types=1);

namespace Heptacom\AdminOpenAuth\Component\Provider;

use Generator;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use Heptacom\AdminOpenAuth\Component\OpenIdConnect\OpenIdConnectRequestHelper;
use Heptacom\AdminOpenAuth\Component\OpenIdConnect\OpenIdConnectService;
use Heptacom\AdminOpenAuth\Component\OpenIdConnect\OpenIdConnectToken;
use Heptacom\AdminOpenAuth\Contract\Client\ClientContract;
use Heptacom\AdminOpenAuth\Contract\RedirectBehaviour;
use Heptacom\AdminOpenAuth\Contract\TokenPair;
use Heptacom\AdminOpenAuth\Contract\User;
use Heptacom\AdminOpenAuth\Service\TokenPairFactoryContract;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;

final class GitHubClient extends ClientContract
{
    public function __construct(
        private readonly TokenPairFactoryContract $tokenPairFactory,
        private readonly OpenIdConnectService $openIdConnectService,
        private readonly ClientInterface $oidcHttpClient,
    ) {
    }

    public function getLoginUrl(?string $state, RedirectBehaviour $behaviour): string
    {
        $state ??= '';
        $params = [];

        if (\is_string($behaviour->redirectUri)) {
            $params['redirect_uri'] = $behaviour->redirectUri;
        }

        if ($state !== '') {
            $params[$behaviour->stateKey] = $state;
        }

        return $this->openIdConnectService->getAuthorizationUrl($params);
    }

    public function refreshToken(string $refreshToken): TokenPair
    {
        return $this->tokenPairFactory->fromOpenIdConnectToken(
            $this->openIdConnectService->getAccessToken('refresh_token', ['refresh_token' => $refreshToken])
        );
    }

    public function getUser(string $state, string $code, RedirectBehaviour $behaviour): User
    {
        $options = [$behaviour->codeKey => $code];

        if (\is_string($behaviour->redirectUri)) {
            $options['redirect_uri'] = $behaviour->redirectUri;
        }

        $token = $this->openIdConnectService->getAccessToken('authorization_code', $options);

        // we cannot use the OpenIdConnectClient because the fields are not reproducible enough
        $userInfo = $this->fetchJson('https://api.github.com/user', $token);

        //TODO: find a good way to setup and specify restriction with rules
        try {
            $userInfo['memberships']['my-org'] = $this->getUserData( "https://api.github.com/orgs/my-org/memberships/{$userInfo['name']}", $token);
        } catch (RequestException) {
        }

        $result = new User();
        $result->primaryKey = (string) ($userInfo['id'] ?? '');
        $result->tokenPair = $this->tokenPairFactory->fromOpenIdConnectToken($token);
        $result->displayName = $userInfo['name'] ?? $userInfo['login'] ?? '';

        $emailAddresses = iterator_to_array($this->fetchEmails($token));
        if ($emailAddresses === []) {
            throw new \RuntimeException('Could not retrieve primary and verified email');
        }
        $result->primaryEmail = $emailAddresses[0];
        $result->emails = $emailAddresses;

        $result->addArrayExtension('resourceOwner', $userInfo);

        return $result;
    }

    public function authorizeRequest(RequestInterface $request, TokenPair $token): RequestInterface
    {
        return $request->withAddedHeader('Authorization', 'Bearer ' . $token->accessToken);
    }

    private function getUserData(string $url, OpenIdConnectToken $token): array
    {
        $data = $this->fetchJson($url, $token);

        return $data;
    }

    private function fetchEmails(OpenIdConnectToken $token): Generator
    {
        $emails = $this->fetchJson('https://api.github.com/user/emails', $token);

        usort($emails, static function (array $a, array $b) {
            if (($a['primary'] ?? false) === ($b['primary'] ?? false)) {
                return 0;
            }

            return ($a['primary'] ?? false) ? -1 : 1;
        });

        foreach ($emails as $entry) {
            if ($entry['verified'] ?? false) {
                yield $entry['email'];
            }
        }
    }

    private function fetchJson(string $url, OpenIdConnectToken $token): array
    {
        $request = OpenIdConnectRequestHelper::prepareRequest(new Request('GET', new Uri($url)), $token);
        $response = $this->oidcHttpClient->sendRequest($request);

        OpenIdConnectRequestHelper::verifyRequestSuccess($request, $response);

        return \json_decode((string) $response->getBody(), true);
    }
}
