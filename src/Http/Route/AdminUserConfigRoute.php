<?php

declare(strict_types=1);

namespace Heptacom\AdminOpenAuth\Http\Route;

use Heptacom\AdminOpenAuth\KskHeptacomAdminOpenAuth;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminUserConfigRoute extends AbstractController
{
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    #[Route(
        path: '/api/_admin/open-auth/config',
        name: 'api.heptacom.admin_open_auth.config',
        defaults: [
            '_routeScope' => ['administration'],
        ],
        methods: ['GET']
    )]
    public function config(): Response
    {
        return new JsonResponse([
            'denyPasswordLogin' => $this->systemConfigService->getBool(KskHeptacomAdminOpenAuth::CONFIG_DENY_PASSWORD_LOGIN),
        ]);
    }
}
