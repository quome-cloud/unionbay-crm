<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.dashboard.index.title')
    </x-slot>

    <!-- Head Details Section -->
    {!! view_render_event('admin.dashboard.index.header.before') !!}

    <div class="mb-5 flex items-center justify-between gap-4 max-sm:flex-wrap">
        {!! view_render_event('admin.dashboard.index.header.left.before') !!}

        <div class="grid gap-1.5">
            <p class="text-2xl font-semibold dark:text-white">
                @lang('admin::app.dashboard.index.title')
            </p>
        </div>

        {!! view_render_event('admin.dashboard.index.header.left.after') !!}

        <!-- Actions -->
        {!! view_render_event('admin.dashboard.index.header.right.before') !!}

        <v-dashboard-filters>
            <!-- Shimmer -->
            <div class="flex gap-1.5">
                <div class="light-shimmer-bg dark:shimmer h-[39px] w-[140px] rounded-md"></div>
                <div class="light-shimmer-bg dark:shimmer h-[39px] w-[140px] rounded-md"></div>
            </div>
        </v-dashboard-filters>

        {!! view_render_event('admin.dashboard.index.header.right.after') !!}
    </div>

    {!! view_render_event('admin.dashboard.index.header.after') !!}

    <!-- Body Component -->
    {!! view_render_event('admin.dashboard.index.content.before') !!}

    <div class="mt-3.5 flex gap-4 max-xl:flex-wrap">
        <!-- Left Section -->
        {!! view_render_event('admin.dashboard.index.content.left.before') !!}

        <div class="flex flex-1 flex-col gap-4 max-xl:flex-auto">
            <!-- Revenue Stats -->
            @include('admin::dashboard.index.revenue')

            <!-- Over All Stats -->
            @include('admin::dashboard.index.over-all')

            <!-- Total Leads Stats -->
            @include('admin::dashboard.index.total-leads')

            <div class="flex gap-4 max-lg:flex-wrap">
                <!-- Total Products -->
                @include('admin::dashboard.index.top-selling-products')

                <!-- Total Persons -->
                @include('admin::dashboard.index.top-persons')
            </div>
        </div>

        {!! view_render_event('admin.dashboard.index.content.left.after') !!}

        <!-- Right Section -->
        {!! view_render_event('admin.dashboard.index.content.right.before') !!}

        <div class="flex w-[378px] max-w-full flex-col gap-4 max-sm:w-full">
            <!-- Revenue by Types -->
            @include('admin::dashboard.index.open-leads-by-states')

            <!-- Revenue by Sources -->
            @include('admin::dashboard.index.revenue-by-sources')

            <!-- Revenue by Types -->
            @include('admin::dashboard.index.revenue-by-types')
        </div>

        {!! view_render_event('admin.dashboard.index.content.left.after') !!}
    </div>

    {!! view_render_event('admin.dashboard.index.content.after') !!}

    @pushOnce('scripts')

        <script
            type="module"
            src="{{ vite()->asset('js/chart.js') }}"
        >
        </script>

        <script
            type="module"
            src="https://cdn.jsdelivr.net/npm/chartjs-chart-funnel@4.2.1/build/index.umd.min.js"
        >
        </script>

        <script
            type="text/x-template"
            id="v-dashboard-filters-template"
        >
            {!! view_render_event('admin.dashboard.index.date_filters.before') !!}

            <div class="flex flex-wrap gap-1.5">
                <!-- User Filter (Manager View) -->
                <select
                    v-if="users.length > 0"
                    v-model="filters.user_id"
                    class="flex min-h-[39px] rounded-md border px-3 py-2 text-sm text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400"
                    data-testid="dashboard-user-filter"
                >
                    <option value="">All Team Members</option>
                    <option
                        v-for="user in users"
                        :key="user.id"
                        :value="user.id"
                    >
                        @{{ user.name }}
                    </option>
                </select>

                <!-- Timeframe Quick Selects -->
                <div class="flex rounded-md border dark:border-gray-800" data-testid="dashboard-timeframe-buttons">
                    <button
                        v-for="tf in timeframes"
                        :key="tf.label"
                        type="button"
                        class="px-3 py-2 text-xs font-medium transition-colors first:rounded-l-md last:rounded-r-md"
                        :class="activeTimeframe === tf.label
                            ? 'bg-brandColor text-white'
                            : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800'"
                        @click="setTimeframe(tf)"
                        :data-testid="'timeframe-' + tf.label.toLowerCase()"
                    >
                        @{{ tf.label }}
                    </button>
                </div>

                <x-admin::flat-picker.date
                    class="!w-[140px]"
                    ::allow-input="false"
                    ::max-date="filters.end"
                >
                    <input
                        class="flex min-h-[39px] w-full rounded-md border px-3 py-2 text-sm text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400"
                        v-model="filters.start"
                        placeholder="@lang('admin::app.dashboard.index.start-date')"
                    />
                </x-admin::flat-picker.date>

                <x-admin::flat-picker.date
                    class="!w-[140px]"
                    ::allow-input="false"
                    ::max-date="filters.end"
                >
                    <input
                        class="flex min-h-[39px] w-full rounded-md border px-3 py-2 text-sm text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400"
                        v-model="filters.end"
                        placeholder="@lang('admin::app.dashboard.index.end-date')"
                    />
                </x-admin::flat-picker.date>
            </div>

            {!! view_render_event('admin.dashboard.index.date_filters.after') !!}
        </script>

        <script type="module">
            app.component('v-dashboard-filters', {
                template: '#v-dashboard-filters-template',

                data() {
                    return {
                        users: [],

                        activeTimeframe: 'Month',

                        timeframes: [
                            { label: 'Week',     days: 7 },
                            { label: 'Month',    days: 30 },
                            { label: 'Quarter',  days: 90 },
                            { label: 'Year',     days: 365 },
                            { label: 'Lifetime', days: 3650 },
                        ],

                        filters: {
                            channel: '',

                            user_id: '',

                            start: "{{ $startDate->format('Y-m-d') }}",

                            end: "{{ $endDate->format('Y-m-d') }}",
                        }
                    }
                },

                mounted() {
                    this.fetchUsers();
                },

                watch: {
                    filters: {
                        handler() {
                            this.$emitter.emit('reporting-filter-updated', this.filters);
                        },

                        deep: true
                    }
                },

                methods: {
                    async fetchUsers() {
                        try {
                            const response = await this.$axios.get('/admin/team-stream/members');
                            this.users = response.data?.data || [];
                        } catch {
                            this.users = [];
                        }
                    },

                    setTimeframe(tf) {
                        this.activeTimeframe = tf.label;
                        const end = new Date();
                        const start = new Date();
                        start.setDate(end.getDate() - tf.days);
                        this.filters.start = start.toISOString().split('T')[0];
                        this.filters.end = end.toISOString().split('T')[0];
                    },
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>
