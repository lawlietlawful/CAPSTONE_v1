@extends(request()->has('modal') ? 'layouts.modal' : 'layouts.counselor')

@section('title', 'Read Notice')
@section('page-title', 'Notice Details')
@section('page-sub', 'View message thread')

@section('content')

<div class="max-w-4xl mx-auto space-y-6">
    <a href="{{ request()->has('modal') ? route('counselor.messages.index', ['modal' => 1]) : route('counselor.messages.index') }}" class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-900 transition-colors">
        <i class="ti ti-arrow-left"></i> Back to Messages
    </a>

    <div class="bg-white rounded-2xl shadow-premium border border-gray-100 overflow-hidden">
        {{-- Thread Header --}}
        <div class="px-8 py-6 border-b border-gray-100 bg-gray-50/30 flex justify-between items-start">
            <div>
                <h2 class="text-xl font-bold text-gray-900">{{ $message->subject ?: 'Notice Thread' }}</h2>
                <div class="flex items-center gap-2 mt-2 text-sm text-gray-500">
                    <span>From: <strong>{{ $message->sender->name }}</strong> ({{ ucfirst($message->sender->role) }})</span>
                    <span>&bull;</span>
                    <span>To: <strong>{{ $message->receiver->name }}</strong> ({{ ucfirst($message->receiver->role) }})</span>
                </div>
            </div>
            <div class="text-xs text-gray-400 bg-white px-3 py-1.5 rounded-lg border border-gray-100 shadow-sm">
                <i class="ti ti-calendar mr-1"></i> {{ $message->created_at->format('M d, Y h:i A') }}
            </div>
        </div>

        <div class="p-8 space-y-8">
            {{-- Original Message --}}
            <div class="flex gap-4">
                <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold flex-shrink-0 mt-1">
                    {{ strtoupper(substr($message->sender->name, 0, 1)) }}
                </div>
                <div class="flex-1">
                    <div class="bg-gray-50 border border-gray-100 rounded-2xl p-5 text-gray-800 text-[15px] leading-relaxed shadow-sm">
                        {!! nl2br(e($message->content)) !!}
                    </div>
                </div>
            </div>

            {{-- Replies --}}
            @foreach($message->replies as $reply)
                <div class="flex gap-4 {{ $reply->sender_id === auth()->id() ? 'flex-row-reverse' : '' }}">
                    <div class="w-10 h-10 rounded-full {{ $reply->sender_id === auth()->id() ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-700' }} flex items-center justify-center font-bold flex-shrink-0 mt-1">
                        {{ strtoupper(substr($reply->sender->name, 0, 1)) }}
                    </div>
                    <div class="flex-1 {{ $reply->sender_id === auth()->id() ? 'text-right' : '' }}">
                        <div class="inline-block {{ $reply->sender_id === auth()->id() ? 'bg-indigo-600 text-white' : 'bg-gray-50 border border-gray-100 text-gray-800' }} rounded-2xl p-5 text-[15px] leading-relaxed shadow-sm text-left max-w-[85%]">
                            {!! nl2br(e($reply->content)) !!}
                        </div>
                        <div class="text-[11px] text-gray-400 mt-2 px-1">
                            {{ $reply->sender->name }} &bull; {{ $reply->created_at->diffForHumans() }}
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Reply Form --}}
        @php
            $lastSenderId = $message->replies->count() > 0 ? $message->replies->last()->sender_id : $message->sender_id;
        @endphp

        @if($lastSenderId !== auth()->id())
            <div class="px-8 py-6 border-t border-gray-100 bg-gray-50/50">
                <form action="{{ request()->has('modal') ? route('counselor.messages.store', ['modal' => 1]) : route('counselor.messages.store') }}" method="POST">
                    @csrf
                    <input type="hidden" name="parent_id" value="{{ $message->id }}">
                    <div class="flex gap-4">
                        <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 font-bold flex-shrink-0">
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        </div>
                        <div class="flex-1 relative">
                            <textarea name="content" rows="3" class="w-full pl-4 pr-16 py-3 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 resize-none shadow-sm" placeholder="Type your reply..." required></textarea>
                            <button type="submit" class="absolute bottom-3 right-3 w-8 h-8 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg flex items-center justify-center transition shadow-sm">
                                <i class="ti ti-send text-sm"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        @else
            <div class="px-8 py-6 border-t border-gray-100 bg-gray-50/50 text-center">
                <p class="text-sm text-gray-500 flex items-center justify-center gap-2">
                    <i class="ti ti-clock"></i> Waiting for the recipient to reply...
                </p>
            </div>
        @endif
    </div>
</div>

@endsection
