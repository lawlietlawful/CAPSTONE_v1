@extends(request()->has('modal') ? 'layouts.modal' : 'layouts.counselor')

@section('title', 'Messages')
@section('page-title', 'Messages & Notices')
@section('page-sub', 'Communicate with students and teachers')

@section('content')

<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

    {{-- Left Sidebar: Compose & Navigation --}}
    <div class="lg:col-span-1 space-y-6">
        <button onclick="document.getElementById('composeModal').classList.remove('hidden')" class="w-full flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-4 rounded-xl shadow-premium transition-all">
            <i class="ti ti-edit text-lg"></i> Compose Notice
        </button>

        <div class="bg-white rounded-xl shadow-premium border border-gray-100 overflow-hidden" x-data="{ activeTab: 'inbox' }">
            <div class="p-2 space-y-1">
                <a href="{{ request()->fullUrlWithQuery(['tab' => 'inbox']) }}" class="flex items-center justify-between px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request('tab', 'inbox') === 'inbox' ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-50' }}">
                    <span class="flex items-center gap-2.5"><i class="ti ti-inbox text-lg"></i> Inbox</span>
                    @if($unreadCount > 0)
                        <span class="bg-blue-100 text-blue-700 py-0.5 px-2 rounded-full text-xs">{{ $unreadCount }}</span>
                    @endif
                </a>
                <a href="{{ request()->fullUrlWithQuery(['tab' => 'sent']) }}" class="flex items-center justify-between px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request('tab') === 'sent' ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-50' }}">
                    <span class="flex items-center gap-2.5"><i class="ti ti-send text-lg"></i> Sent</span>
                </a>
            </div>
        </div>
    </div>

    {{-- Right Content: Message List --}}
    <div class="lg:col-span-3">
        <div class="bg-white rounded-2xl shadow-premium border border-gray-100 overflow-hidden">
            @php $messages = request('tab') === 'sent' ? $sent : $inbox; @endphp
            
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center gap-4">
                <h3 class="text-[15px] font-semibold text-gray-800 flex items-center gap-2 whitespace-nowrap">
                    <i class="ti ti-{{ request('tab') === 'sent' ? 'send' : 'inbox' }} text-gray-400"></i>
                    {{ request('tab') === 'sent' ? 'Sent Notices' : 'Inbox' }}
                </h3>
                <form method="GET" class="relative w-full max-w-xs" id="filterForm">
                    <input type="hidden" name="tab" value="{{ request('tab', 'inbox') }}">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="ti ti-search text-gray-400 text-sm"></i>
                    </div>
                    <input type="text" id="searchInput" name="search" value="{{ $search }}" placeholder="Search name or message..."
                        class="w-full pl-9 pr-3 py-1.5 rounded-lg border border-gray-200 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition text-xs text-gray-900 shadow-sm">
                </form>
            </div>

            <div class="divide-y divide-gray-100">
                @forelse($messages as $msg)
                    <div x-data="{ showDeleteModal: false, deleted: false }" x-show="!deleted" class="relative flex items-start gap-4 p-4 hover:bg-gray-50 transition-colors border-b border-gray-50 group {{ is_null($msg->read_at) && request('tab', 'inbox') === 'inbox' ? 'bg-blue-50/30' : '' }}">
                        <a href="{{ request()->has('modal') ? route('counselor.messages.show', [$msg->id, 'modal' => 1]) : route('counselor.messages.show', $msg->id) }}" class="absolute inset-0 z-0"></a>
                        
                        <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold flex-shrink-0 relative z-10 pointer-events-none">
                            {{ strtoupper(substr(request('tab') === 'sent' ? ($msg->receiver->name ?? '?') : ($msg->sender->name ?? '?'), 0, 1)) }}
                        </div>
                        <div class="flex-1 min-w-0 relative z-10 pointer-events-none">
                            <div class="flex justify-between items-start mb-0.5">
                                <h4 class="text-sm font-semibold {{ is_null($msg->read_at) && request('tab', 'inbox') === 'inbox' ? 'text-gray-900' : 'text-gray-700' }} truncate">
                                    {{ request('tab') === 'sent' ? 'To: ' . ($msg->receiver->name ?? 'Unknown') : ($msg->sender->name ?? 'Unknown') }}
                                    <span class="text-[10px] ml-2 px-2 py-0.5 rounded-full {{ request('tab') === 'sent' ? ($msg->receiver->role === 'teacher' ? 'bg-purple-100 text-purple-700' : 'bg-emerald-100 text-emerald-700') : ($msg->sender->role === 'teacher' ? 'bg-purple-100 text-purple-700' : 'bg-emerald-100 text-emerald-700') }}">
                                        {{ ucfirst(request('tab') === 'sent' ? ($msg->receiver->role ?? 'user') : ($msg->sender->role ?? 'user')) }}
                                    </span>
                                </h4>
                                <span class="text-[11px] text-gray-400 whitespace-nowrap">{{ $msg->created_at->diffForHumans() }}</span>
                            </div>

                            @if($msg->subject)
                                <p class="text-xs font-medium text-gray-700 truncate mt-0.5">{{ $msg->subject }}</p>
                            @endif
                            <p class="text-xs text-gray-500 truncate mt-1">{{ Str::limit($msg->content, 80) }}</p>
                        </div>
                        
                        <div class="relative z-10 opacity-0 group-hover:opacity-100 transition-opacity ml-2">
                            <button type="button" @click="showDeleteModal = true" class="p-1.5 text-gray-400 hover:text-red-600 rounded-lg hover:bg-red-50 transition tooltip" data-tip="Delete Conversation">
                                <i class="ti ti-trash text-lg"></i>
                            </button>
                        </div>

                        <!-- Custom Delete Confirmation Modal using Alpine teleport -->
                        <template x-teleport="body">
                            <div x-show="showDeleteModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-gray-900/50 backdrop-blur-sm" style="display: none;">
                                <div @click.away="showDeleteModal = false" class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 transform transition-all relative">
                                    <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center text-red-600 mb-4">
                                        <i class="ti ti-alert-triangle text-2xl"></i>
                                    </div>
                                    <h3 class="text-lg font-bold text-gray-900 mb-2">Delete Conversation?</h3>
                                    <p class="text-sm text-gray-500 mb-6">This action cannot be undone. The conversation will be removed from your view.</p>
                                    <div class="flex justify-end gap-3">
                                        <button @click="showDeleteModal = false" type="button" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                                        <button @click="
                                            fetch('{{ route('counselor.messages.destroy', $msg->id) }}', {
                                                method: 'DELETE',
                                                headers: {
                                                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                                    'Accept': 'application/json'
                                                }
                                            }).then(res => {
                                                if(res.ok) {
                                                    showDeleteModal = false;
                                                    deleted = true;
                                                }
                                            })
                                        " type="button" class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700">Delete</button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                @empty
                    <div class="p-12 text-center">
                        <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center text-gray-300 mx-auto mb-4">
                            <i class="ti ti-inbox text-3xl"></i>
                        </div>
                        <p class="text-gray-900 font-medium">No messages found.</p>
                        <p class="text-gray-500 text-sm mt-1">
                            {{ $search ? 'No results for "' . $search . '".' : 'Your ' . request('tab', 'inbox') . ' is empty.' }}
                        </p>
                    </div>
                @endforelse
            </div>
            
            @if($messages->hasPages())
                <div class="px-6 py-4 border-t border-gray-100 bg-gray-50/50">
                    {{ $messages->appends(request()->query())->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Compose Modal --}}
<div id="composeModal" class="hidden fixed inset-0 z-[100] flex items-center justify-center bg-gray-900/50 backdrop-blur-sm transition-opacity">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden transform transition-all">
        <form action="{{ request()->has('modal') ? route('counselor.messages.store', ['modal' => 1]) : route('counselor.messages.store') }}" method="POST"
              x-data="{
                    query: '',
                    open: false,
                    selectedId: '',
                    selectedLabel: '',
                    error: false,
                    recipients: [
                        @foreach($teachers as $teacher)
                            { id: {{ $teacher->id }}, name: @js($teacher->name), role: 'Teacher' },
                        @endforeach
                        @foreach($students as $student)
                            { id: {{ $student->id }}, name: @js($student->name), role: 'Student' },
                        @endforeach
                    ],
                    get filtered() {
                        if (!this.query || this.query === this.selectedLabel) return this.recipients;
                        const q = this.query.toLowerCase();
                        return this.recipients.filter(r => r.name.toLowerCase().includes(q));
                    },
                    select(r) {
                        this.selectedId = r.id;
                        this.selectedLabel = r.name + ' (' + r.role + ')';
                        this.query = this.selectedLabel;
                        this.open = false;
                        this.error = false;
                    },
                    validate(e) {
                        if (!this.selectedId) {
                            e.preventDefault();
                            this.error = true;
                            this.open = true;
                        }
                    }
              }"
              @submit="validate">
            @csrf
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                <h3 class="text-[15px] font-semibold text-gray-900">Compose Notice</h3>
                <button type="button" onclick="document.getElementById('composeModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600 transition">
                    <i class="ti ti-x text-xl"></i>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1.5">Recipient</label>
                    <input type="hidden" name="receiver_id" :value="selectedId">
                    <div class="relative" @click.away="open = false">
                        <input type="text" x-model="query" @focus="open = true" @input="if (query !== selectedLabel) { selectedId = ''; error = false; }"
                            placeholder="Search a student or teacher..." autocomplete="off"
                            class="w-full rounded-xl border px-4 py-2.5 text-sm text-gray-900 shadow-sm bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition"
                            :class="error ? 'border-red-400' : 'border-gray-300'">
                        <div x-show="open" x-cloak class="absolute z-20 mt-1 w-full max-h-56 overflow-y-auto bg-white border border-gray-200 rounded-xl shadow-lg py-1">
                            <template x-for="r in filtered" :key="r.id">
                                <button type="button" @click="select(r)" class="w-full text-left px-4 py-2 text-sm hover:bg-blue-50 flex items-center justify-between gap-2">
                                    <span x-text="r.name"></span>
                                    <span class="text-[10px] px-2 py-0.5 rounded-full" :class="r.role === 'Teacher' ? 'bg-purple-100 text-purple-700' : 'bg-emerald-100 text-emerald-700'" x-text="r.role"></span>
                                </button>
                            </template>
                            <div x-show="filtered.length === 0" class="px-4 py-3 text-sm text-gray-400">No matches found.</div>
                        </div>
                    </div>
                    <p x-show="error" x-cloak class="text-xs text-red-500 mt-1">Please pick a recipient from the list.</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1.5">Subject <span class="normal-case text-gray-400 font-normal">(optional)</span></label>
                    <input type="text" name="subject" maxlength="255" class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm" placeholder="What is this about?">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1.5">Message</label>
                    <textarea name="content" rows="5" class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm resize-none" placeholder="Type your notice here..." required></textarea>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('composeModal').classList.add('hidden')" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">Cancel</button>
                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition shadow-sm flex items-center gap-2">
                    <i class="ti ti-send text-base"></i> Send Notice
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    let searchTimeout = null;
    const searchInput = document.getElementById('searchInput');
    const filterForm = document.getElementById('filterForm');

    if (searchInput && filterForm) {
        searchInput.addEventListener('input', function(e) {
            clearTimeout(searchTimeout);
            const val = e.target.value.trim();

            // Auto submit if cleared or if length >= 2
            if (val.length === 0 || val.length >= 2) {
                searchTimeout = setTimeout(() => {
                    filterForm.submit();
                }, 500); // Wait 500ms after user stops typing
            }
        });

        // Put cursor at the end of text when page reloads with search value
        if (searchInput.value) {
            const length = searchInput.value.length;
            searchInput.focus();
            searchInput.setSelectionRange(length, length);
        }
    }
</script>

@endsection
