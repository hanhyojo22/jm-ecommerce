window.chatInbox = (userId) => ({
    channel: null,
    connection: null,
    onConnected: null,
    onReady: null,
    onOnline: null,

    init() {
        this.onReady = () => this.connect();
        this.onOnline = () => this.$wire.$refresh();
        window.addEventListener('reverb:ready', this.onReady);
        window.addEventListener('online', this.onOnline);
        this.connect();
    },

    connect() {
        if (!window.Echo || this.channel) return;

        this.channel = window.Echo.private(`chat.inbox.${userId}`);
        this.channel.listen('.inbox.changed', () => this.$wire.$refresh());
        this.connection = window.Echo.connector.pusher.connection;
        this.onConnected = () => this.$wire.$refresh();
        this.connection.bind('connected', this.onConnected);
        if (this.connection.state === 'connected') this.onConnected();
    },

    destroy() {
        window.removeEventListener('reverb:ready', this.onReady);
        window.removeEventListener('online', this.onOnline);
        this.connection?.unbind('connected', this.onConnected);
        if (this.channel) window.Echo?.leave(`chat.inbox.${userId}`);
    },
});

window.chatThread = (conversationId, userId) => ({
    pending: [],
    online: navigator.onLine,
    selectedType: null,
    fileName: '',
    fileSize: '',
    previewUrl: null,
    attachmentMenuOpen: false,
    uploading: false,
    uploadProgress: 0,
    uploadError: '',
    activeUploadId: null,
    observer: null,
    onOnline: null,
    onOffline: null,
    onReady: null,
    onFocus: null,
    connection: null,
    onConnected: null,
    onStateChange: null,
    channel: null,
    presence: null,
    onlineUsers: [],
    liveConnected: false,
    previousMessageScroll: null,
    loadingOlder: false,
    historyError: '',
    followLatestMessage: true,
    onMessageScroll: null,
    unhook: null,
    typingTimer: null,

    init() {
        this.onOnline = () => {
            this.online = true;
            this.$wire.syncLatestMessages();
        };
        this.onOffline = () => { this.online = false; };
        this.onReady = () => this.connect();
        this.onFocus = () => { if (this.online) this.$wire.syncLatestMessages(); };
        window.addEventListener('online', this.onOnline);
        window.addEventListener('offline', this.onOffline);
        window.addEventListener('reverb:ready', this.onReady);
        window.addEventListener('focus', this.onFocus);
        this.observer = new MutationObserver(() => {
            this.reconcile();
            if (this.previousMessageScroll) requestAnimationFrame(() => this.restoreOlderScroll());
        });
        this.observer.observe(this.$refs.messageList, { childList: true, subtree: true });
        this.onMessageScroll = () => {
            const list = this.$refs.messageList;
            this.followLatestMessage = list.scrollHeight - list.clientHeight - list.scrollTop < 120;
        };
        this.$refs.messageList.addEventListener('scroll', this.onMessageScroll);
        this.unhook = Livewire.hook('morphed', ({ el }) => {
            if (el !== this.$el && !el.contains(this.$el)) return;
            requestAnimationFrame(() => {
                this.localizeTimes();
                if (this.previousMessageScroll) {
                    this.restoreOlderScroll();
                } else if (this.followLatestMessage) {
                    this.scrollToLatest();
                }
                this.reconcile();
            });
        });
        requestAnimationFrame(() => {
            this.localizeTimes();
            this.scrollToLatest();
        });
        this.connect();
    },

    destroy() {
        window.removeEventListener('online', this.onOnline);
        window.removeEventListener('offline', this.onOffline);
        window.removeEventListener('reverb:ready', this.onReady);
        window.removeEventListener('focus', this.onFocus);
        this.$refs.messageList.removeEventListener('scroll', this.onMessageScroll);
        this.unhook?.();
        this.connection?.unbind('connected', this.onConnected);
        this.connection?.unbind('state_change', this.onStateChange);
        if (this.channel) window.Echo?.leave(`conversation.${conversationId}`);
        clearTimeout(this.typingTimer);
        this.observer?.disconnect();
        for (const item of this.pending) {
            if (item.previewUrl) URL.revokeObjectURL(item.previewUrl);
        }
        if (this.previewUrl && !this.pending.some((item) => item.previewUrl === this.previewUrl)) {
            URL.revokeObjectURL(this.previewUrl);
        }
    },

    connect() {
        if (!window.Echo || this.channel) return;

        this.channel = window.Echo.private(`conversation.${conversationId}`);
        this.channel.listen('.message.created', (event) => {
            this.$wire.syncLatestMessages();
            if (event.message?.sender_id !== userId) {
                this.$wire.markDelivered();
                this.$wire.markRead();
            }
        });
        this.channel.listen('.message.read', () => this.$wire.$refresh());
        this.channel.listen('.message.delivered', () => this.$wire.$refresh());
        this.channel.listen('.conversation.typing', (event) => {
            if (event.user_id === userId) return;
            const indicator = this.$refs.typingIndicator;
            indicator.classList.toggle('hidden', !event.typing);
            clearTimeout(this.typingTimer);
            if (event.typing) this.typingTimer = setTimeout(() => indicator.classList.add('hidden'), 4000);
        });
        this.presence = window.Echo.join(`conversation.${conversationId}`)
            .here((users) => {
                this.onlineUsers = users.filter((user) => user.id !== userId);
                this.setPresence();
            })
            .joining((user) => {
                if (user.id !== userId && !this.onlineUsers.some((onlineUser) => onlineUser.id === user.id)) {
                    this.onlineUsers.push(user);
                }
                this.setPresence();
            })
            .leaving((user) => {
                this.onlineUsers = this.onlineUsers.filter((onlineUser) => onlineUser.id !== user.id);
                this.setPresence();
            });
        this.connection = window.Echo.connector.pusher.connection;
        this.onConnected = () => {
            this.liveConnected = true;
            this.$wire.syncLatestMessages();
            this.$wire.markDelivered();
            this.$wire.markRead();
        };
        this.onStateChange = ({ current }) => { this.liveConnected = current === 'connected'; };
        this.connection.bind('connected', this.onConnected);
        this.connection.bind('state_change', this.onStateChange);
        if (this.connection.state === 'connected') this.onConnected();
    },

    setPresence() {
        window.dispatchEvent(new CustomEvent('chat-presence-status', {
            detail: { conversationId, online: this.onlineUsers.length > 0 },
        }));
    },

    localizeTimes() {
        const list = this.$refs.messageList;
        const today = new Date();
        const yesterday = new Date(today);
        yesterday.setDate(today.getDate() - 1);
        const timeFormatter = new Intl.DateTimeFormat('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
        const dateFormatter = new Intl.DateTimeFormat('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
        let previousLocalDate = null;

        list.querySelectorAll('[data-chat-date-separator]').forEach((separator) => {
            const date = new Date(separator.dataset.chatDate);
            const localDate = date.toDateString();
            separator.classList.toggle('hidden', localDate === previousLocalDate);
            separator.querySelector('[data-chat-date-label]').textContent = localDate === today.toDateString()
                ? 'Today' : localDate === yesterday.toDateString() ? 'Yesterday' : dateFormatter.format(date);
            previousLocalDate = localDate;
        });
        list.querySelectorAll('time[data-chat-local-time]').forEach((time) => {
            time.textContent = timeFormatter.format(new Date(time.dateTime));
        });
    },

    scrollToLatest() {
        this.$refs.messageList.scrollTo({ top: this.$refs.messageList.scrollHeight, behavior: 'auto' });
    },

    restoreOlderScroll() {
        const previous = this.previousMessageScroll;
        if (!previous) return;
        const difference = this.$refs.messageList.scrollHeight - previous.height;
        if (difference === 0) return;
        this.$refs.messageList.scrollTop = previous.top + difference;
        this.previousMessageScroll = null;
    },

    async loadOlder() {
        if (this.loadingOlder) return;
        this.loadingOlder = true;
        this.historyError = '';
        this.followLatestMessage = false;
        this.previousMessageScroll = {
            top: this.$refs.messageList.scrollTop,
            height: this.$refs.messageList.scrollHeight,
        };
        try {
            await this.$wire.loadOlderMessages();
            this.restoreOlderScroll();
        } catch {
            this.previousMessageScroll = null;
            this.historyError = 'Older messages could not be loaded. Try again.';
        } finally {
            this.loadingOlder = false;
        }
    },

    reconcile() {
        const confirmedIds = new Set([...this.$refs.messageList.querySelectorAll('article[data-client-message-id]')]
            .map((element) => element.dataset.clientMessageId));
        this.pending = this.pending.filter((item) => {
            if (!confirmedIds.has(item.id)) return true;
            if (this.$refs.messageBody.value === item.body) {
                this.$refs.messageBody.value = '';
                this.$wire.body = '';
            }
            if (item.previewUrl) URL.revokeObjectURL(item.previewUrl);
            return false;
        });
    },

    selectFile(type, event) {
        const file = event.target.files[0];
        const replaced = this.pending.filter((item) => item.file && item.status === 'Failed');
        for (const item of replaced) {
            if (item.previewUrl) URL.revokeObjectURL(item.previewUrl);
        }
        this.pending = this.pending.filter((item) => !replaced.includes(item));
        if (this.previewUrl && !this.pending.some((item) => item.previewUrl === this.previewUrl)) URL.revokeObjectURL(this.previewUrl);
        this.$refs[type === 'photo' ? 'documentInput' : 'photoInput'].value = '';
        this.selectedType = file ? type : null;
        this.fileName = file?.name ?? '';
        this.fileSize = file ? (file.size < 1024 ? `${file.size} B` : `${(file.size / 1024).toFixed(1)} KB`) : '';
        this.previewUrl = file && type === 'photo' ? URL.createObjectURL(file) : null;
        this.attachmentMenuOpen = false;
        this.uploadError = '';
    },

    clearFile(keepPreview = false) {
        if (this.previewUrl && !keepPreview && !this.pending.some((item) => item.previewUrl === this.previewUrl)) {
            URL.revokeObjectURL(this.previewUrl);
        }
        this.$refs.photoInput.value = '';
        this.$refs.documentInput.value = '';
        this.selectedType = null;
        this.fileName = '';
        this.fileSize = '';
        this.previewUrl = null;
    },

    submitMessage() {
        if (!this.online || this.uploading) return;
        const body = this.$refs.messageBody.value.trim();
        if (!body && !this.selectedType) {
            this.uploadError = 'Write a message or choose an attachment.';
            return;
        }

        const type = this.selectedType;
        const file = type ? this.$refs[type === 'photo' ? 'photoInput' : 'documentInput'].files[0] : null;
        if (type && !file) {
            this.uploadError = 'Choose a file before sending.';
            return;
        }
        const failedMessage = this.pending.find((item) => item.status === 'Failed' && item.file === file && item.body === body);
        if (failedMessage) {
            this.$refs.messageBody.value = '';
            this.$wire.body = '';
            this.retry(failedMessage);
            return;
        }
        const item = {
            id: crypto.randomUUID(), body, file, fileType: type, fileName: file?.name ?? '',
            previewUrl: this.previewUrl, progress: file ? 0 : null, status: 'Sending',
            time: new Intl.DateTimeFormat('en-US', { hour: 'numeric', minute: '2-digit' }).format(new Date()),
        };
        this.pending.push(item);
        this.uploadError = '';
        this.$refs.messageBody.value = '';
        this.$wire.body = '';
        this.$nextTick(() => this.$refs.messageList.scrollTo({ top: this.$refs.messageList.scrollHeight }));

        if (file) {
            this.submitAttachment(item);
        } else {
            this.submitText(item);
        }
    },

    submitText(item) {
        const attempt = (item.attempt ?? 0) + 1;
        item.attempt = attempt;
        const timeout = setTimeout(() => {
            const pending = this.pending.find((entry) => entry.id === item.id);
            if (pending?.attempt === attempt && pending.status === 'Sending') this.failText(pending);
        }, 8000);
        this.$wire.send(item.body, item.id).then((result) => {
            clearTimeout(timeout);
            const pending = this.pending.find((entry) => entry.id === item.id);
            if (!pending || pending.attempt !== attempt) return;
            if (result?.id) {
                pending.status = 'Sent';
                if (this.$refs.messageBody.value === pending.body) {
                    this.$refs.messageBody.value = '';
                    this.$wire.body = '';
                }
            } else this.failText(pending);
            this.$nextTick(() => this.reconcile());
        }).catch(() => {
            clearTimeout(timeout);
            const pending = this.pending.find((entry) => entry.id === item.id);
            if (pending?.attempt === attempt) this.failText(pending);
        });
    },

    failText(item) {
        item.status = 'Failed';
        this.uploadError = 'Message could not be sent. Check your connection and use Retry.';
        if (!this.$refs.messageBody.value) {
            this.$refs.messageBody.value = item.body;
            this.$wire.body = item.body;
        }
    },

    submitAttachment(item) {
        this.uploading = true;
        this.activeUploadId = item.id;
        item.status = 'Sending';
        item.progress = 0;
        const input = this.$refs[item.fileType === 'photo' ? 'photoInput' : 'documentInput'];
        if (input.files[0] !== item.file && item.file) {
            const transfer = new DataTransfer();
            transfer.items.add(item.file);
            input.files = transfer.files;
        }
        const form = this.$refs[item.fileType === 'photo' ? 'photoForm' : 'documentForm'];
        form.querySelector('[name="client_message_id"]').value = item.id;
        form.querySelector('[name="body"]').value = item.body;
        form.requestSubmit();
    },

    attachmentProgress(progress) {
        this.uploadProgress = progress;
        const item = this.pending.find((entry) => entry.id === this.activeUploadId);
        if (item) item.progress = progress;
    },

    attachmentFailed(message) {
        this.uploading = false;
        this.uploadError = message;
        const item = this.pending.find((entry) => entry.id === this.activeUploadId);
        if (item) {
            item.status = 'Failed';
            if (!this.$refs.messageBody.value) {
                this.$refs.messageBody.value = item.body;
                this.$wire.body = item.body;
            }
        }
    },

    attachmentSent(result) {
        this.uploading = false;
        const item = this.pending.find((entry) => entry.id === result.client_message_id);
        if (item) item.status = 'Sent';
        if (item && this.$refs.messageBody.value === item.body) {
            this.$refs.messageBody.value = '';
            this.$wire.body = '';
        }
        this.clearFile(true);
        this.$wire.syncLatestMessages();
    },

    retry(item) {
        if (!this.online || item.status !== 'Failed') return;
        this.uploadError = '';
        item.status = 'Sending';
        if (item.file) {
            this.submitAttachment(item);
        } else {
            this.submitText(item);
        }
    },
});
