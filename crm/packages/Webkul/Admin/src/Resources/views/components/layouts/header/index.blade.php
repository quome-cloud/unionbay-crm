<header class="sticky top-0 z-[10001] flex items-center justify-between gap-1 border-b border-gray-200 bg-white px-4 py-2 transition-all [backface-visibility:hidden] dark:border-gray-800 dark:bg-gray-900">  
    <!-- logo -->
    <div class="flex items-center gap-1.5">
        <!-- Sidebar Menu -->
        <x-admin::layouts.sidebar.mobile />
        
        @php
            $wlSettings = \Webkul\WhiteLabel\Models\WhiteLabelSetting::first();
            $wlLogo = request()->cookie('dark_mode') ? ($wlSettings?->logo_dark_url ?? $wlSettings?->logo_url) : $wlSettings?->logo_url;
            $wlAppName = $wlSettings?->app_name ?? config('app.name');
        @endphp
        <a href="{{ route('admin.dashboard.index') }}">
            @if ($wlLogo)
                <img
                    class="h-10"
                    src="{{ $wlLogo }}"
                    alt="{{ $wlAppName }}"
                />
            @elseif ($logo = core()->getConfigData('general.general.admin_logo.logo_image'))
                <img
                    class="h-10"
                    src="{{ Storage::url($logo) }}"
                    alt="{{ $wlAppName }}"
                />
            @else
                <img
                    class="h-10 max-sm:hidden"
                    src="{{ request()->cookie('dark_mode') ? vite()->asset('images/dark-logo.svg') : vite()->asset('images/logo.svg') }}"
                    id="logo-image"
                    alt="{{ $wlAppName }}"
                />

                <img
                    class="h-10 sm:hidden"
                    src="{{ request()->cookie('dark_mode') ? vite()->asset('images/mobile-dark-logo.svg') : vite()->asset('images/mobile-light-logo.svg') }}"
                    id="logo-image"
                    alt="{{ $wlAppName }}"
                />
            @endif
        </a>
    </div>

    <div class="flex items-center gap-1.5 max-md:hidden">
        <!-- Mega Search Bar -->
        @include('admin::components.layouts.header.desktop.mega-search')

        <!-- Quick Creation Bar -->
        @include('admin::components.layouts.header.quick-creation')
    </div>

    <div class="flex items-center gap-2.5">
        <div class="md:hidden">
            <!-- Mega Search Bar -->
            @include('admin::components.layouts.header.mobile.mega-search')
        </div>
        
        <!-- Dark mode -->
        <v-dark>
            <div class="flex">
                <span
                    class="{{ request()->cookie('dark_mode') ? 'icon-light' : 'icon-dark' }} p-1.5 rounded-md text-2xl cursor-pointer transition-all hover:bg-gray-100 dark:hover:bg-gray-950"
                ></span>
            </div>
        </v-dark>

        <div class="md:hidden">
            <!-- Quick Creation Bar -->
            @include('admin::components.layouts.header.quick-creation')
        </div>

        <!-- Notification Bell -->
        <v-notification-bell data-testid="notification-bell"></v-notification-bell>

        <!-- Admin profile -->
        <x-admin::dropdown position="bottom-{{ in_array(app()->getLocale(), ['fa', 'ar']) ? 'left' : 'right' }}">
            <x-slot:toggle>
                @php($user = auth()->guard('user')->user())

                @if ($user->image)
                    <button class="flex h-9 w-9 cursor-pointer overflow-hidden rounded-full hover:opacity-80 focus:opacity-80">
                        <img
                            src="{{ $user->image_url }}"
                            class="h-full w-full object-cover"
                        />
                    </button>
                @else
                    <button class="flex h-9 w-9 cursor-pointer items-center justify-center rounded-full bg-pink-400 font-semibold leading-6 text-white">
                        {{ substr($user->name, 0, 1) }}
                    </button>
                @endif
            </x-slot>

            <!-- Admin Dropdown -->
            <x-slot:content class="mt-2 border-t-0 !p-0">
                <div class="flex items-center gap-1.5 border border-x-0 border-b-gray-300 px-5 py-2.5 dark:border-gray-800">
                    @if ($wlLogo)
                        <img
                            src="{{ $wlLogo }}"
                            class="h-6 w-6 object-contain"
                        />
                    @else
                        <img
                            src="{{ vite()->asset('images/logo.svg') }}"
                            class="h-6 w-6 object-contain"
                        />
                    @endif

                    <!-- Version -->
                    <p class="text-gray-400">
                        @lang('admin::app.layouts.app-version', ['version' => core()->version()])
                    </p>
                </div>

                <div class="grid gap-1 pb-2.5">
                    <a
                        class="cursor-pointer px-5 py-2 text-base text-gray-800 hover:bg-gray-100 dark:text-white dark:hover:bg-gray-950"
                        href="{{ route('admin.user.account.edit') }}"
                    >
                        @lang('admin::app.layouts.my-account')
                    </a>

                    <!--Admin logout-->
                    <x-admin::form
                        method="DELETE"
                        action="{{ route('admin.session.destroy') }}"
                        id="adminLogout"
                    >
                    </x-admin::form>

                    <a
                        class="cursor-pointer px-5 py-2 text-base text-gray-800 hover:bg-gray-100 dark:text-white dark:hover:bg-gray-950"
                        href="{{ route('admin.session.destroy') }}"
                        onclick="event.preventDefault(); document.getElementById('adminLogout').submit();"
                    >
                        @lang('admin::app.layouts.sign-out')
                    </a>
                </div>
            </x-slot>
        </x-admin::dropdown>
    </div>
</header>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-notification-bell-template"
    >
        <div class="relative">
            <button
                type="button"
                class="relative flex cursor-pointer rounded-md p-1.5 text-2xl transition-all hover:bg-gray-100 dark:hover:bg-gray-950"
                @click="toggleDropdown"
                data-testid="notification-bell-btn"
            >
                <span class="icon-notification"></span>
                <span
                    v-if="unreadCount > 0"
                    class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white"
                    data-testid="notification-unread-badge"
                >
                    @{{ unreadCount > 99 ? '99+' : unreadCount }}
                </span>
            </button>

            <!-- Dropdown -->
            <div
                v-if="isOpen"
                class="absolute right-0 top-full z-50 mt-2 w-80 rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-800 dark:bg-gray-900"
                data-testid="notification-dropdown"
            >
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-800">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Notifications</h3>
                    <button
                        v-if="unreadCount > 0"
                        type="button"
                        class="text-xs font-medium text-blue-600 hover:underline dark:text-blue-400"
                        @click="markAllRead"
                        data-testid="notification-mark-all-read"
                    >
                        Mark all read
                    </button>
                </div>

                <!-- Notification List -->
                <div class="max-h-80 overflow-y-auto" data-testid="notification-list">
                    <div v-if="isLoading" class="p-4 text-center text-sm text-gray-400">
                        Loading...
                    </div>

                    <div v-else-if="notifications.length === 0" class="p-8 text-center" data-testid="notification-empty">
                        <span class="icon-notification text-4xl text-gray-300 dark:text-gray-600"></span>
                        <p class="mt-2 text-sm text-gray-400 dark:text-gray-500">No notifications</p>
                    </div>

                    <div
                        v-else
                        v-for="notification in notifications"
                        :key="notification.id"
                        class="flex cursor-pointer gap-3 border-b border-gray-100 px-4 py-3 transition-all hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800"
                        :class="{ 'bg-blue-50 dark:bg-blue-900/10': !notification.read_at }"
                        @click="markRead(notification)"
                        data-testid="notification-item"
                    >
                        <!-- Type Icon -->
                        <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full" :class="typeIconBg(notification.type)">
                            <span :class="typeIcon(notification.type)" class="text-sm text-white"></span>
                        </div>

                        <!-- Content -->
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-gray-900 dark:text-white" :class="{ 'font-bold': !notification.read_at }">
                                @{{ notification.title }}
                            </p>
                            <p v-if="notification.body" class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">
                                @{{ notification.body }}
                            </p>
                            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                @{{ timeAgo(notification.created_at) }}
                            </p>
                        </div>

                        <!-- Unread Dot -->
                        <div v-if="!notification.read_at" class="mt-2 h-2 w-2 flex-shrink-0 rounded-full bg-blue-500"></div>
                    </div>
                </div>
            </div>

            <!-- Backdrop -->
            <div
                v-if="isOpen"
                class="fixed inset-0 z-40"
                @click="isOpen = false"
            ></div>
        </div>
    </script>

    <script
        type="text/x-template"
        id="v-dark-template"
    >
        <div class="flex">
            <span
                class="cursor-pointer rounded-md p-1.5 text-2xl transition-all hover:bg-gray-100 dark:hover:bg-gray-950"
                :class="[isDarkMode ? 'icon-light' : 'icon-dark']"
                @click="toggle"
            ></span>
        </div>
    </script>

    <script type="module">
        app.component('v-dark', {
            template: '#v-dark-template',

            data() {
                return {
                    isDarkMode: {{ request()->cookie('dark_mode') ?? 0 }},

                    logo: "{{ vite()->asset('images/logo.svg') }}",

                    dark_logo: "{{ vite()->asset('images/dark-logo.svg') }}",
                };
            },

            methods: {
                toggle() {
                    this.isDarkMode = parseInt(this.isDarkModeCookie()) ? 0 : 1;

                    var expiryDate = new Date();

                    expiryDate.setMonth(expiryDate.getMonth() + 1);

                    document.cookie = 'dark_mode=' + this.isDarkMode + '; path=/; expires=' + expiryDate.toGMTString();

                    document.documentElement.classList.toggle('dark', this.isDarkMode === 1);

                    if (this.isDarkMode) {
                        this.$emitter.emit('change-theme', 'dark');

                        document.getElementById('logo-image').src = this.dark_logo;
                    } else {
                        this.$emitter.emit('change-theme', 'light');

                        document.getElementById('logo-image').src = this.logo;
                    }
                },

                isDarkModeCookie() {
                    const cookies = document.cookie.split(';');

                    for (const cookie of cookies) {
                        const [name, value] = cookie.trim().split('=');

                        if (name === 'dark_mode') {
                            return value;
                        }
                    }

                    return 0;
                },
            },
        });
    </script>

    <script type="module">
        app.component('v-notification-bell', {
            template: '#v-notification-bell-template',

            data() {
                return {
                    isOpen: false,
                    isLoading: false,
                    unreadCount: 0,
                    notifications: [],
                    pollInterval: null,
                };
            },

            mounted() {
                this.fetchUnreadCount();
                // Poll for new notifications every 30 seconds
                this.pollInterval = setInterval(() => this.fetchUnreadCount(), 30000);
            },

            beforeUnmount() {
                if (this.pollInterval) {
                    clearInterval(this.pollInterval);
                }
            },

            methods: {
                async fetchUnreadCount() {
                    try {
                        const response = await this.$axios.get('/api/v1/notifications/unread-count');
                        this.unreadCount = response.data?.data?.unread_count || 0;
                    } catch (error) {
                        // Silently fail
                    }
                },

                async fetchNotifications() {
                    this.isLoading = true;
                    try {
                        const response = await this.$axios.get('/api/v1/notifications?per_page=20');
                        this.notifications = response.data?.data || [];
                    } catch (error) {
                        this.notifications = [];
                    } finally {
                        this.isLoading = false;
                    }
                },

                toggleDropdown() {
                    this.isOpen = !this.isOpen;
                    if (this.isOpen) {
                        this.fetchNotifications();
                    }
                },

                async markRead(notification) {
                    if (!notification.read_at) {
                        try {
                            await this.$axios.put(`/api/v1/notifications/${notification.id}/read`);
                            notification.read_at = new Date().toISOString();
                            this.unreadCount = Math.max(0, this.unreadCount - 1);
                        } catch (error) {}
                    }

                    // Navigate based on notification type/data
                    if (notification.data) {
                        const data = typeof notification.data === 'string' ? JSON.parse(notification.data) : notification.data;
                        if (data.url) {
                            window.location.href = data.url;
                        }
                    }
                },

                async markAllRead() {
                    try {
                        await this.$axios.put('/api/v1/notifications/read-all');
                        this.notifications.forEach(n => n.read_at = new Date().toISOString());
                        this.unreadCount = 0;
                    } catch (error) {}
                },

                typeIcon(type) {
                    return {
                        comment_mention: 'icon-mail',
                        lead_stage_change: 'icon-activity',
                        deal_assignment: 'icon-leads',
                        action_reminder: 'icon-clock',
                        email_received: 'icon-mail',
                    }[type] || 'icon-notification';
                },

                typeIconBg(type) {
                    return {
                        comment_mention: 'bg-blue-500',
                        lead_stage_change: 'bg-green-500',
                        deal_assignment: 'bg-purple-500',
                        action_reminder: 'bg-orange-500',
                        email_received: 'bg-cyan-500',
                    }[type] || 'bg-gray-500';
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
