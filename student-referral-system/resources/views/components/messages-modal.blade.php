{{--
    Global Messages popup: any page can open it by dispatching the
    `open-messages` window event —
      @click="$dispatch('open-messages', { to: 123 })"      opens Compose, prefilled
      @click="$dispatch('open-messages', { thread: 45 })"   opens that thread directly
    Shared by layouts.admin and layouts.counselor so every page (not just the
    counselor's own section) can send a notice, or revisit one, without
    leaving where it is.
--}}
<div x-data="{ showMessages: false, prefillTo: null, prefillThread: null }"
     @open-messages.window="showMessages = true; prefillTo = $event.detail?.to ?? null; prefillThread = $event.detail?.thread ?? null"
     @keydown.escape.window="showMessages = false"
     @message.window="if ($event.origin === window.location.origin && $event.data === 'close-messages') showMessages = false"
     x-cloak
     class="relative z-[100]"
     aria-labelledby="modal-title"
     role="dialog"
     aria-modal="true">

    <div x-show="showMessages"
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm transition-opacity"></div>

    <div x-show="showMessages" class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div x-show="showMessages"
                 @click.away="showMessages = false"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-5xl h-[85vh] flex flex-col">

                {{-- Modal Body (Iframe) --}}
                <div class="flex-1 relative w-full h-full rounded-2xl overflow-hidden bg-white">
                    <template x-if="showMessages">
                        <iframe :src="prefillThread
                                    ? '{{ route('counselor.messages.show', ['message' => '__ID__', 'modal' => 1]) }}'.replace('__ID__', prefillThread)
                                    : (prefillTo
                                        ? '{{ route('counselor.messages.index', ['modal' => 1]) }}&to=' + prefillTo
                                        : '{{ route('counselor.messages.index', ['modal' => 1]) }}')"
                                class="absolute inset-0 w-full h-full border-0"></iframe>
                    </template>
                </div>
            </div>
        </div>
    </div>
</div>
