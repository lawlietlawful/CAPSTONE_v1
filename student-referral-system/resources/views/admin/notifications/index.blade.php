@extends('layouts.admin')

@section('title', 'Notifications')
@section('page-title', 'Notifications')
@section('page-sub', 'Updates on referrals that need your attention')

@section('content')

<div class="bg-white rounded-2xl shadow-premium border border-gray-100 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
        <h3 class="text-[15px] font-semibold text-gray-800 flex items-center gap-2">
            <i class="ti ti-bell text-gray-400"></i> All Notifications
        </h3>
        @if($unreadCount > 0)
            <form method="POST" action="{{ route('admin.notifications.markAllRead') }}">
                @csrf
                <button type="submit" class="text-xs font-medium text-blue-600 hover:text-blue-700 bg-blue-50 hover:bg-blue-100 px-3 py-1.5 rounded-lg transition-colors">
                    Mark all as read
                </button>
            </form>
        @endif
    </div>

    <div class="divide-y divide-gray-100">
        @forelse($notifications as $notification)
            <a href="{{ route('admin.notifications.show', $notification->id) }}"
               class="flex items-start gap-4 p-4 hover:bg-gray-50 transition-colors {{ !$notification->is_read ? 'bg-blue-50/30' : '' }}">
                <div class="w-9 h-9 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 flex-shrink-0 mt-0.5">
                    <i class="ti ti-file-text text-sm"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex justify-between items-start gap-2">
                        <h4 class="text-sm font-semibold {{ !$notification->is_read ? 'text-gray-900' : 'text-gray-600' }} truncate">
                            {{ $notification->title }}
                        </h4>
                        <span class="text-[11px] text-gray-400 whitespace-nowrap">{{ $notification->created_at->diffForHumans() }}</span>
                    </div>
                    <p class="text-xs text-gray-500 mt-0.5">{{ $notification->message }}</p>
                </div>
                @if(!$notification->is_read)
                    <span class="w-2 h-2 rounded-full bg-blue-500 flex-shrink-0 mt-2" title="Unread"></span>
                @endif
            </a>
        @empty
            <div class="p-12 text-center">
                <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center text-gray-300 mx-auto mb-4">
                    <i class="ti ti-bell-off text-3xl"></i>
                </div>
                <p class="text-gray-900 font-medium">No notifications yet.</p>
                <p class="text-gray-500 text-sm mt-1">You'll see updates here when a referral needs your attention.</p>
            </div>
        @endforelse
    </div>

    @if($notifications->hasPages())
        <div class="px-6 py-4 border-t border-gray-100 bg-gray-50/50">
            {{ $notifications->links() }}
        </div>
    @endif
</div>

@endsection
