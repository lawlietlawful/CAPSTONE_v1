{{--
    Shared header bell for all three role layouts (admin, counselor, teacher).
    Included with:
      @include('components.notification-bell', ['prefix' => 'admin'|'teacher', 'accent' => 'blue'|'amber'])
    Optional $buttonClass/$badgeClass override the bell icon button's and the
    unread dot's classes, since the teacher layout's button looks different
    (a filled circle button) from admin/counselor's (a bare icon).

    Polls its role's notifications.poll endpoint every 20s so a notification
    created while the page is already open (e.g. a teacher files a referral
    while the counselor is looking at the dashboard) shows up without a
    manual refresh — the whole reason this was rebuilt as a client-rendered
    dropdown instead of the original static Blade list.
--}}
@php
    $buttonClass ??= 'relative text-gray-400 hover:text-gray-600 focus:outline-none';
    $badgeClass ??= 'absolute -top-0.5 -right-0.5 w-2 h-2 bg-red-500 rounded-full border-2 border-white';
@endphp
<div class="relative"
     x-data="{
        open: false,
        unreadCount: {{ (int) ($unreadNotifications ?? 0) }},
        notifications: @js($recentNotifications ?? []),
        poll() {
            fetch('{{ route($prefix . '.notifications.poll') }}', { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(data => { this.unreadCount = data.unread_count; this.notifications = data.notifications; })
                .catch(() => {});
        },
        markAllRead() {
            fetch('{{ route($prefix . '.notifications.markAllRead') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                },
            }).then(() => this.poll());
        },
     }"
     x-init="setInterval(() => poll(), 20000)"
     @click.away="open = false">

    <button @click="open = !open" type="button" class="{{ $buttonClass }}">
        <i class="ti ti-bell text-xl"></i>
        <span x-show="unreadCount > 0" x-cloak class="{{ $badgeClass }}"></span>
    </button>

    <div x-show="open" x-transition.opacity style="display: none;" x-cloak
         class="absolute right-0 mt-2 w-80 bg-white rounded-xl shadow-lg border border-gray-100 z-30 overflow-hidden text-left">
        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
            <h4 class="text-sm font-semibold text-gray-800">Notifications</h4>
            <button x-show="unreadCount > 0" x-cloak @click="markAllRead()" type="button" class="text-xs font-medium text-{{ $accent }}-600 hover:text-{{ $accent }}-700">
                Mark all read
            </button>
        </div>
        <div class="max-h-80 overflow-y-auto divide-y divide-gray-50">
            <template x-if="notifications.length === 0">
                <div class="px-4 py-8 text-center">
                    <p class="text-xs text-gray-400">No notifications yet.</p>
                </div>
            </template>
            <template x-for="n in notifications" :key="n.id">
                <a :href="'{{ url($prefix . '/notifications') }}/' + n.id"
                   class="block px-4 py-3 hover:bg-gray-50 transition"
                   :class="!n.is_read ? 'bg-{{ $accent }}-50/40' : ''">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-xs font-semibold truncate" :class="!n.is_read ? 'text-gray-900' : 'text-gray-600'" x-text="n.title"></p>
                        <span class="text-[10px] text-gray-400 whitespace-nowrap flex-shrink-0" x-text="n.time_ago"></span>
                    </div>
                    <p class="text-[11px] text-gray-500 mt-0.5 line-clamp-2" x-text="n.message"></p>
                </a>
            </template>
        </div>
        <a href="{{ route($prefix . '.notifications.index') }}" class="block text-center text-xs font-medium text-{{ $accent }}-600 hover:text-{{ $accent }}-700 py-2.5 border-t border-gray-100 hover:bg-gray-50 transition">
            View all
        </a>
    </div>
</div>
