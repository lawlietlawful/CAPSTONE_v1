<div x-data="{ 
        open: false, 
        formId: '', 
        title: 'Confirm Action', 
        message: 'Are you sure?', 
        confirmText: 'Yes, Confirm',
        buttonClass: 'bg-red-600 hover:bg-red-700 shadow-red-200',
        iconClass: 'ti-alert-triangle text-red-600',
        iconBgClass: 'bg-red-50'
    }"
    @open-confirm-modal.window="
        open = true; 
        formId = $event.detail.formId;
        title = $event.detail.title || 'Confirm Action';
        message = $event.detail.message || 'Are you sure you want to proceed?';
        confirmText = $event.detail.confirmText || 'Confirm';
        buttonClass = $event.detail.buttonClass || 'bg-red-600 hover:bg-red-700 shadow-red-200';
        iconClass = $event.detail.iconClass || 'ti-alert-triangle text-red-600';
        iconBgClass = $event.detail.iconBgClass || 'bg-red-50';
    ">
    <div x-show="open" class="fixed inset-0 z-[100] overflow-y-auto" style="display: none;" x-cloak>
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="open" x-transition.opacity class="fixed inset-0 transition-opacity bg-gray-900/60 backdrop-blur-sm" @click="open = false"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div x-show="open" x-transition.scale.origin.bottom class="inline-block w-full max-w-md p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-premium rounded-2xl sm:p-8">
                <div class="sm:flex sm:items-start">
                    <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 mx-auto rounded-full sm:mx-0 sm:h-10 sm:w-10" :class="iconBgClass">
                        <i class="ti text-xl" :class="iconClass"></i>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <h3 class="text-lg font-bold text-gray-900" x-text="title"></h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500" x-text="message"></p>
                        </div>
                    </div>
                </div>
                <div class="mt-6 sm:mt-8 sm:flex sm:flex-row-reverse gap-3">
                    <button type="button" class="w-full inline-flex justify-center rounded-xl border border-transparent px-4 py-2 text-base font-semibold text-white shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 sm:ml-3 sm:w-auto sm:text-sm transition flex items-center justify-center gap-2"
                        :class="buttonClass"
                        @click="document.getElementById(formId).submit(); open = false;">
                        <span x-text="confirmText"></span>
                    </button>
                    <button type="button" class="mt-3 w-full inline-flex justify-center rounded-xl border border-gray-300 bg-white px-4 py-2 text-base font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-200 focus:ring-offset-2 sm:mt-0 sm:w-auto sm:text-sm transition" @click="open = false">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
