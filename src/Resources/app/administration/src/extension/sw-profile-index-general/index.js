import template from './sw-profile-index-general.html.twig';

const { Component } = Shopware;

Component.override('sw-profile-index-general', {
    template,

    inject: [
        'repositoryFactory',
        'loginService'
    ],

    data() {
        return {
            denyPasswordLogin: false,
            userId: null,
        }
    },

    created() {
        const clientRepository = this.repositoryFactory.create('heptacom_admin_open_auth_client');
        const headers = clientRepository.buildHeaders(Shopware.Context.api);

        clientRepository.httpClient
            .get('/_admin/open-auth/config', { headers })
            .then((response) => {
                this.denyPasswordLogin = response.data.denyPasswordLogin;
            });

        this.userId = Shopware.Store.get('session')?.currentUser.id;
    },
});
