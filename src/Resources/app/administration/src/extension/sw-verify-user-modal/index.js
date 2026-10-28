import template from './sw-verify-user-modal.html.twig';

const { Component } = Shopware;

Component.override('sw-verify-user-modal', {
    template,

    inject: ['repositoryFactory'],

    data() {
        return {
            denyPasswordLogin: false,
        };
    },

    created() {
        const clientRepository = this.repositoryFactory.create('heptacom_admin_open_auth_client');
        const headers = clientRepository.buildHeaders(Shopware.Context.api);

        clientRepository.httpClient
            .get('/_admin/open-auth/config', { headers })
            .then((response) => {
                this.denyPasswordLogin = response.data.denyPasswordLogin;
            });
    },

    methods: {
        onCloseConfirmPasswordModal() {
            this.$refs.heptacomAdminOpenAuthUserConfirmLogin.abortAuthFlow();

            return this.$super('onCloseConfirmPasswordModal');
        },

        heptacomAdminOpenAuthUserConfirm(context) {
            this.$emit('verified', context);
        }
    }
});
