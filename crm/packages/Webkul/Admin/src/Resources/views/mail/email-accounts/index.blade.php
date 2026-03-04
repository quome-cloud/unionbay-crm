<x-admin::layouts>
    <x-slot:title>
        Email Accounts
    </x-slot>

    <div class="flex flex-col gap-4" data-testid="email-accounts-page">
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-2">
                <div class="text-xl font-bold dark:text-white">
                    Email Accounts
                </div>
                <p class="text-gray-500 dark:text-gray-400">Connect your email accounts to sync messages with the CRM.</p>
            </div>

            <div class="flex items-center gap-x-2.5">
                <button
                    type="button"
                    class="primary-button"
                    @click="$refs.emailAccountManager.openCreateModal()"
                    data-testid="email-accounts-add-btn"
                >
                    Add Email Account
                </button>
            </div>
        </div>

        <v-email-account-manager ref="emailAccountManager"></v-email-account-manager>
    </div>

    @pushOnce('scripts')
        <script
            type="text/x-template"
            id="v-email-account-manager-template"
        >
            <div>
                <!-- Account List -->
                <div v-if="isLoading" class="rounded-lg border border-gray-200 bg-white p-8 text-center dark:border-gray-800 dark:bg-gray-900">
                    <p class="text-gray-400">Loading accounts...</p>
                </div>

                <div v-else-if="accounts.length === 0" class="rounded-lg border border-gray-200 bg-white p-8 text-center dark:border-gray-800 dark:bg-gray-900" data-testid="email-accounts-empty">
                    <span class="icon-mail text-5xl text-gray-300 dark:text-gray-600"></span>
                    <p class="mt-4 text-lg font-medium text-gray-600 dark:text-gray-400">No email accounts connected</p>
                    <p class="mt-1 text-sm text-gray-400 dark:text-gray-500">Add your first email account to start syncing messages.</p>
                    <button
                        type="button"
                        class="primary-button mt-4"
                        @click="openCreateModal"
                    >
                        Add Email Account
                    </button>
                </div>

                <div v-else class="grid gap-4" data-testid="email-accounts-list">
                    <div
                        v-for="account in accounts"
                        :key="account.id"
                        class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900"
                        data-testid="email-account-card"
                    >
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-3">
                                <!-- Provider Icon -->
                                <div class="flex h-10 w-10 items-center justify-center rounded-full" :class="providerBg(account.provider)">
                                    <span :class="providerIcon(account.provider)" class="text-lg text-white"></span>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-900 dark:text-white">
                                        @{{ account.display_name || account.email_address }}
                                    </h4>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">@{{ account.email_address }}</p>
                                    <div class="mt-1 flex items-center gap-2">
                                        <span
                                            class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="statusBadge(account.status)"
                                            data-testid="email-account-status"
                                        >
                                            @{{ account.status }}
                                        </span>
                                        <span class="text-xs text-gray-400 capitalize" data-testid="email-account-provider">
                                            @{{ account.provider }}
                                        </span>
                                        <span v-if="account.last_sync_at" class="text-xs text-gray-400">
                                            Last synced: @{{ timeAgo(account.last_sync_at) }}
                                        </span>
                                    </div>
                                    <p v-if="account.last_error" class="mt-1 text-xs text-red-500" data-testid="email-account-error">
                                        @{{ account.last_error }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    class="rounded-md px-3 py-1.5 text-sm font-medium text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-900/20"
                                    @click="testConnection(account)"
                                    :disabled="account.testing"
                                    data-testid="email-account-test-btn"
                                >
                                    @{{ account.testing ? 'Testing...' : 'Test Connection' }}
                                </button>
                                <button
                                    type="button"
                                    class="rounded-md px-3 py-1.5 text-sm font-medium text-green-600 hover:bg-green-50 dark:text-green-400 dark:hover:bg-green-900/20"
                                    @click="syncAccount(account)"
                                    :disabled="account.syncing || account.status !== 'active'"
                                    data-testid="email-account-sync-btn"
                                >
                                    @{{ account.syncing ? 'Syncing...' : 'Sync Now' }}
                                </button>
                                <button
                                    type="button"
                                    class="rounded-md px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800"
                                    @click="editAccount(account)"
                                    data-testid="email-account-edit-btn"
                                >
                                    Edit
                                </button>
                                <button
                                    type="button"
                                    class="rounded-md px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20"
                                    @click="deleteAccount(account)"
                                    data-testid="email-account-delete-btn"
                                >
                                    Delete
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Create/Edit Modal -->
                <x-admin::modal ref="accountModal">
                    <x-slot:header>
                        <h3 class="text-lg font-bold text-gray-800 dark:text-white" data-testid="email-account-modal-title">
                            @{{ editingAccount ? 'Edit Email Account' : 'Add Email Account' }}
                        </h3>
                    </x-slot>

                    <x-slot:content>
                        <div class="flex flex-col gap-4 p-4">
                            <!-- Provider Quick Select -->
                            <div v-if="!editingAccount" class="flex gap-3" data-testid="email-account-provider-select">
                                <button
                                    v-for="p in providers"
                                    :key="p.value"
                                    type="button"
                                    class="flex flex-1 flex-col items-center gap-2 rounded-lg border-2 p-4 transition-all"
                                    :class="form.provider === p.value ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 hover:border-gray-300 dark:border-gray-700'"
                                    @click="selectProvider(p.value)"
                                >
                                    <div class="flex h-8 w-8 items-center justify-center rounded-full" :class="providerBg(p.value)">
                                        <span :class="providerIcon(p.value)" class="text-sm text-white"></span>
                                    </div>
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">@{{ p.label }}</span>
                                </button>
                            </div>

                            <!-- Email Address -->
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Email Address *</label>
                                <input
                                    type="email"
                                    v-model="form.email_address"
                                    class="w-full rounded-md border border-gray-200 px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                                    placeholder="you@example.com"
                                    data-testid="email-account-form-email"
                                />
                            </div>

                            <!-- Display Name -->
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Display Name</label>
                                <input
                                    type="text"
                                    v-model="form.display_name"
                                    class="w-full rounded-md border border-gray-200 px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                                    placeholder="My Work Email"
                                    data-testid="email-account-form-name"
                                />
                            </div>

                            <!-- IMAP Settings -->
                            <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700" data-testid="email-account-imap-section">
                                <h4 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">IMAP Settings (Incoming)</h4>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="mb-1 block text-xs text-gray-500">Host *</label>
                                        <input
                                            type="text"
                                            v-model="form.imap_host"
                                            class="w-full rounded border border-gray-200 px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                            data-testid="email-account-form-imap-host"
                                        />
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs text-gray-500">Port</label>
                                        <input
                                            type="number"
                                            v-model.number="form.imap_port"
                                            class="w-full rounded border border-gray-200 px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                            data-testid="email-account-form-imap-port"
                                        />
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs text-gray-500">Username *</label>
                                        <input
                                            type="text"
                                            v-model="form.imap_username"
                                            class="w-full rounded border border-gray-200 px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                            data-testid="email-account-form-imap-username"
                                        />
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs text-gray-500">Password *</label>
                                        <input
                                            type="password"
                                            v-model="form.imap_password"
                                            class="w-full rounded border border-gray-200 px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                            :placeholder="editingAccount ? '(unchanged)' : ''"
                                            data-testid="email-account-form-imap-password"
                                        />
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs text-gray-500">Encryption</label>
                                        <select
                                            v-model="form.imap_encryption"
                                            class="w-full rounded border border-gray-200 px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                            data-testid="email-account-form-imap-encryption"
                                        >
                                            <option value="ssl">SSL</option>
                                            <option value="tls">TLS</option>
                                            <option value="none">None</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- SMTP Settings -->
                            <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700" data-testid="email-account-smtp-section">
                                <h4 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">SMTP Settings (Outgoing)</h4>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="mb-1 block text-xs text-gray-500">Host *</label>
                                        <input
                                            type="text"
                                            v-model="form.smtp_host"
                                            class="w-full rounded border border-gray-200 px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                            data-testid="email-account-form-smtp-host"
                                        />
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs text-gray-500">Port</label>
                                        <input
                                            type="number"
                                            v-model.number="form.smtp_port"
                                            class="w-full rounded border border-gray-200 px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                            data-testid="email-account-form-smtp-port"
                                        />
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs text-gray-500">Username *</label>
                                        <input
                                            type="text"
                                            v-model="form.smtp_username"
                                            class="w-full rounded border border-gray-200 px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                            data-testid="email-account-form-smtp-username"
                                        />
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs text-gray-500">Password *</label>
                                        <input
                                            type="password"
                                            v-model="form.smtp_password"
                                            class="w-full rounded border border-gray-200 px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                            :placeholder="editingAccount ? '(unchanged)' : ''"
                                            data-testid="email-account-form-smtp-password"
                                        />
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs text-gray-500">Encryption</label>
                                        <select
                                            v-model="form.smtp_encryption"
                                            class="w-full rounded border border-gray-200 px-2 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                            data-testid="email-account-form-smtp-encryption"
                                        >
                                            <option value="ssl">SSL</option>
                                            <option value="tls">TLS</option>
                                            <option value="none">None</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Sync Days -->
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Sync History (days)</label>
                                <input
                                    type="number"
                                    v-model.number="form.sync_days"
                                    min="1"
                                    max="365"
                                    class="w-32 rounded-md border border-gray-200 px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                                    data-testid="email-account-form-sync-days"
                                />
                            </div>

                            <!-- Error message -->
                            <div v-if="formError" class="rounded-md bg-red-50 p-3 text-sm text-red-600 dark:bg-red-900/20 dark:text-red-400" data-testid="email-account-form-error">
                                @{{ formError }}
                            </div>
                        </div>
                    </x-slot>

                    <x-slot:footer>
                        <div class="flex justify-end gap-3 px-4 py-3">
                            <button
                                type="button"
                                class="rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800"
                                @click="closeModal"
                                data-testid="email-account-form-cancel"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                class="primary-button"
                                @click="saveAccount"
                                :disabled="isSaving"
                                data-testid="email-account-form-save"
                            >
                                @{{ isSaving ? 'Saving...' : (editingAccount ? 'Update Account' : 'Add Account') }}
                            </button>
                        </div>
                    </x-slot>
                </x-admin::modal>

                <!-- Delete Confirmation Modal -->
                <x-admin::modal ref="deleteModal">
                    <x-slot:header>
                        <h3 class="text-lg font-bold text-gray-800 dark:text-white">Delete Email Account</h3>
                    </x-slot>

                    <x-slot:content>
                        <div class="p-4">
                            <p class="text-gray-600 dark:text-gray-400">
                                Are you sure you want to remove <strong>@{{ deletingAccount?.email_address }}</strong>?
                                This will disconnect the account from the CRM.
                            </p>
                        </div>
                    </x-slot>

                    <x-slot:footer>
                        <div class="flex justify-end gap-3 px-4 py-3">
                            <button
                                type="button"
                                class="rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800"
                                @click="$refs.deleteModal.close()"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700"
                                @click="confirmDelete"
                                data-testid="email-account-delete-confirm"
                            >
                                Delete Account
                            </button>
                        </div>
                    </x-slot>
                </x-admin::modal>

                <!-- Test Result Modal -->
                <x-admin::modal ref="testResultModal">
                    <x-slot:header>
                        <h3 class="text-lg font-bold text-gray-800 dark:text-white">Connection Test Result</h3>
                    </x-slot>

                    <x-slot:content>
                        <div class="p-4" data-testid="email-account-test-result">
                            <div class="flex flex-col gap-3">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="flex h-6 w-6 items-center justify-center rounded-full text-xs text-white"
                                        :class="testResult.imap_ok ? 'bg-green-500' : 'bg-red-500'"
                                    >
                                        @{{ testResult.imap_ok ? '✓' : '✗' }}
                                    </span>
                                    <span class="text-sm text-gray-700 dark:text-gray-300">IMAP Connection</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span
                                        class="flex h-6 w-6 items-center justify-center rounded-full text-xs text-white"
                                        :class="testResult.smtp_ok ? 'bg-green-500' : 'bg-red-500'"
                                    >
                                        @{{ testResult.smtp_ok ? '✓' : '✗' }}
                                    </span>
                                    <span class="text-sm text-gray-700 dark:text-gray-300">SMTP Connection</span>
                                </div>
                                <p v-if="testResult.error" class="mt-2 text-sm text-red-500">@{{ testResult.error }}</p>
                            </div>
                        </div>
                    </x-slot>

                    <x-slot:footer>
                        <div class="flex justify-end px-4 py-3">
                            <button
                                type="button"
                                class="primary-button"
                                @click="$refs.testResultModal.close()"
                            >
                                OK
                            </button>
                        </div>
                    </x-slot>
                </x-admin::modal>
            </div>
        </script>

        <script type="module">
            app.component('v-email-account-manager', {
                template: '#v-email-account-manager-template',

                data() {
                    return {
                        accounts: [],
                        isLoading: true,
                        isSaving: false,
                        editingAccount: null,
                        deletingAccount: null,
                        formError: null,
                        testResult: { imap_ok: false, smtp_ok: false, error: null },
                        form: this.defaultForm(),
                        providers: [
                            { value: 'gmail', label: 'Gmail' },
                            { value: 'outlook', label: 'Outlook' },
                            { value: 'custom', label: 'Custom IMAP' },
                        ],
                    };
                },

                mounted() {
                    this.fetchAccounts();
                },

                methods: {
                    defaultForm() {
                        return {
                            email_address: '',
                            display_name: '',
                            provider: 'gmail',
                            imap_host: 'imap.gmail.com',
                            imap_port: 993,
                            imap_encryption: 'ssl',
                            imap_username: '',
                            imap_password: '',
                            smtp_host: 'smtp.gmail.com',
                            smtp_port: 587,
                            smtp_encryption: 'tls',
                            smtp_username: '',
                            smtp_password: '',
                            sync_days: 30,
                        };
                    },

                    async fetchAccounts() {
                        this.isLoading = true;
                        try {
                            const response = await this.$axios.get('/api/v1/email-accounts');
                            this.accounts = (response.data?.data || []).map(a => ({ ...a, testing: false, syncing: false }));
                        } catch (error) {
                            this.accounts = [];
                        } finally {
                            this.isLoading = false;
                        }
                    },

                    selectProvider(provider) {
                        this.form.provider = provider;
                        if (provider === 'gmail') {
                            this.form.imap_host = 'imap.gmail.com';
                            this.form.imap_port = 993;
                            this.form.imap_encryption = 'ssl';
                            this.form.smtp_host = 'smtp.gmail.com';
                            this.form.smtp_port = 587;
                            this.form.smtp_encryption = 'tls';
                        } else if (provider === 'outlook') {
                            this.form.imap_host = 'outlook.office365.com';
                            this.form.imap_port = 993;
                            this.form.imap_encryption = 'ssl';
                            this.form.smtp_host = 'smtp.office365.com';
                            this.form.smtp_port = 587;
                            this.form.smtp_encryption = 'tls';
                        } else {
                            this.form.imap_host = '';
                            this.form.imap_port = 993;
                            this.form.imap_encryption = 'ssl';
                            this.form.smtp_host = '';
                            this.form.smtp_port = 587;
                            this.form.smtp_encryption = 'tls';
                        }
                    },

                    openCreateModal() {
                        this.editingAccount = null;
                        this.form = this.defaultForm();
                        this.formError = null;
                        this.$refs.accountModal.open();
                    },

                    editAccount(account) {
                        this.editingAccount = account;
                        this.form = {
                            email_address: account.email_address,
                            display_name: account.display_name || '',
                            provider: account.provider || 'custom',
                            imap_host: account.imap_host || '',
                            imap_port: account.imap_port || 993,
                            imap_encryption: account.imap_encryption || 'ssl',
                            imap_username: account.imap_username || account.email_address,
                            imap_password: '',
                            smtp_host: account.smtp_host || '',
                            smtp_port: account.smtp_port || 587,
                            smtp_encryption: account.smtp_encryption || 'tls',
                            smtp_username: account.smtp_username || account.email_address,
                            smtp_password: '',
                            sync_days: account.sync_days || 30,
                        };
                        this.formError = null;
                        this.$refs.accountModal.open();
                    },

                    closeModal() {
                        this.$refs.accountModal.close();
                    },

                    async saveAccount() {
                        this.formError = null;

                        if (!this.form.email_address || !this.form.imap_host || !this.form.smtp_host) {
                            this.formError = 'Please fill in email address, IMAP host, and SMTP host.';
                            return;
                        }

                        this.isSaving = true;

                        try {
                            const data = { ...this.form };
                            if (!data.imap_username) data.imap_username = data.email_address;
                            if (!data.smtp_username) data.smtp_username = data.email_address;

                            if (this.editingAccount) {
                                // Don't send empty passwords on update
                                if (!data.imap_password) delete data.imap_password;
                                if (!data.smtp_password) delete data.smtp_password;

                                await this.$axios.put(`/api/v1/email-accounts/${this.editingAccount.id}`, data);
                            } else {
                                if (!data.imap_password || !data.smtp_password) {
                                    this.formError = 'Passwords are required for new accounts.';
                                    this.isSaving = false;
                                    return;
                                }
                                await this.$axios.post('/api/v1/email-accounts', data);
                            }

                            this.$refs.accountModal.close();
                            await this.fetchAccounts();
                        } catch (error) {
                            this.formError = error.response?.data?.message || 'Failed to save account.';
                        } finally {
                            this.isSaving = false;
                        }
                    },

                    deleteAccount(account) {
                        this.deletingAccount = account;
                        this.$refs.deleteModal.open();
                    },

                    async confirmDelete() {
                        if (!this.deletingAccount) return;

                        try {
                            await this.$axios.delete(`/api/v1/email-accounts/${this.deletingAccount.id}`);
                            this.$refs.deleteModal.close();
                            await this.fetchAccounts();
                        } catch (error) {
                            // Silently fail
                        }
                    },

                    async testConnection(account) {
                        account.testing = true;
                        try {
                            const response = await this.$axios.post(`/api/v1/email-accounts/${account.id}/test`);
                            this.testResult = response.data?.data || { imap_ok: false, smtp_ok: false };
                            this.$refs.testResultModal.open();
                            await this.fetchAccounts();
                        } catch (error) {
                            this.testResult = { imap_ok: false, smtp_ok: false, error: 'Connection test failed.' };
                            this.$refs.testResultModal.open();
                        } finally {
                            account.testing = false;
                        }
                    },

                    async syncAccount(account) {
                        account.syncing = true;
                        try {
                            await this.$axios.post(`/api/v1/email-accounts/${account.id}/sync`);
                            await this.fetchAccounts();
                        } catch (error) {
                            // Silently fail
                        } finally {
                            account.syncing = false;
                        }
                    },

                    providerIcon(provider) {
                        return {
                            gmail: 'icon-mail',
                            outlook: 'icon-mail',
                            custom: 'icon-settings',
                        }[provider] || 'icon-mail';
                    },

                    providerBg(provider) {
                        return {
                            gmail: 'bg-red-500',
                            outlook: 'bg-blue-600',
                            custom: 'bg-gray-500',
                        }[provider] || 'bg-gray-500';
                    },

                    statusBadge(status) {
                        return {
                            active: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                            disabled: 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
                            error: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                        }[status] || 'bg-gray-100 text-gray-600';
                    },

                    timeAgo(dateStr) {
                        if (!dateStr) return '';
                        const date = new Date(dateStr);
                        const now = new Date();
                        const diffMs = now - date;
                        const diffMins = Math.floor(diffMs / 60000);
                        const diffHours = Math.floor(diffMs / 3600000);
                        const diffDays = Math.floor(diffMs / 86400000);

                        if (diffMins < 1) return 'Just now';
                        if (diffMins < 60) return `${diffMins}m ago`;
                        if (diffHours < 24) return `${diffHours}h ago`;
                        if (diffDays < 7) return `${diffDays}d ago`;
                        return date.toLocaleDateString();
                    },
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>
