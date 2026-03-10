<?php

declare(strict_types=1);

namespace Heptacom\AdminOpenAuth\Service\Provider;

use Heptacom\AdminOpenAuth\Component\OpenIdConnect\OpenIdConnectConfiguration;
use Heptacom\AdminOpenAuth\Component\OpenIdConnect\OpenIdConnectService;
use Heptacom\AdminOpenAuth\Component\Provider\GitHubClient;
use Heptacom\AdminOpenAuth\Contract\Client\ClientContract;
use Heptacom\AdminOpenAuth\Contract\ClientProvider\ClientProviderContract;
use Heptacom\AdminOpenAuth\Service\TokenPairFactoryContract;
use Psr\Http\Client\ClientInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class GitHubProvider extends ClientProviderContract
{
    public const PROVIDER_NAME = 'github';

    public function __construct(
        private readonly TokenPairFactoryContract $tokenPairFactory,
        private readonly OpenIdConnectService $openIdConnectService,
        private readonly ClientInterface $oidcHttpClient,
    ) {
    }

    public function provides(): string
    {
        return static::PROVIDER_NAME;
    }

    public function getConfigurationTemplate(): OptionsResolver
    {
        return parent::getConfigurationTemplate()
            ->setDefined([
                'id',
                'clientId',
                'clientSecret',
                'scopes',
            ])->setRequired([
                'clientId',
                'clientSecret',
            ])->setDefaults([
                'scopes' => [],
            ])->setAllowedTypes('clientId', 'string')
            ->setAllowedTypes('clientSecret', 'string')
            ->setAllowedTypes('scopes', 'array')
            ->addNormalizer('scopes', static function (Options $options, $value) {
                return \array_unique(\array_merge((array) $value, [
                    'read:user',
                    'user:email',
                    'read:org',
                ]));
            });
    }

    public function getInitialConfiguration(): array
    {
        $result = parent::getInitialConfiguration();

        $result['clientId'] = '';
        $result['clientSecret'] = '';

        return $result;
    }

    public function provideClient(array $resolvedConfig): ClientContract
    {
        $config = new OpenIdConnectConfiguration();
        $config->setAuthorizationEndpoint('https://github.com/login/oauth/authorize');
        $config->setTokenEndpoint('https://github.com/login/oauth/access_token');
        $config->setClientId($resolvedConfig['clientId']);
        $config->setClientSecret($resolvedConfig['clientSecret']);

        $resolvedConfig['scopes'] = \array_unique(\array_merge($resolvedConfig['scopes'], [
            'read:user',
            'user:email',
            'read:org',
        ]));
        $config->setScopes($resolvedConfig['scopes']);

        $service = $this->openIdConnectService->createWithConfig($config);

        return new GitHubClient($this->tokenPairFactory, $service, $this->oidcHttpClient);
    }
}
