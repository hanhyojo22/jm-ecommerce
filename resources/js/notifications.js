window.notificationBell = (userId) => ({
    channel: null,
    onReady: null,

    init() {
        this.onReady = () => this.connect();
        window.addEventListener('reverb:ready', this.onReady);
        this.connect();
    },

    connect() {
        if (!window.Echo || this.channel) return;

        this.channel = window.Echo.private(`App.Models.User.${userId}`);
        this.channel.notification(() => this.$wire.$refresh());
    },

    destroy() {
        window.removeEventListener('reverb:ready', this.onReady);
        if (this.channel) window.Echo?.leave(`App.Models.User.${userId}`);
    },
});
