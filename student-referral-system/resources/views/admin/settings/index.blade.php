@extends('layouts.admin')

@section('title', 'System Settings')
@section('page-title', 'System Settings')
@section('page-sub', 'Configure application parameters, SMS integration, and Early Warning System thresholds')

@section('content')

<form action="{{ route('admin.settings.update') }}" method="POST">
    @csrf

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Left Nav (Optional, for visual weight) -->
        <div class="lg:col-span-1 space-y-2">
            <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-4">
                <nav class="space-y-1">
                    <a href="#general" class="block px-3 py-2 rounded-lg text-sm font-medium bg-blue-50 text-blue-700">General Settings</a>
                    <a href="#sms" class="block px-3 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50 transition">SMS Integration</a>
                    <a href="#ews" class="block px-3 py-2 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50 transition">Early Warning System</a>
                    <a href="#ml" class="block px-3 py-2 rounded-lg text-sm font-medium text-indigo-600 hover:bg-indigo-50 transition">Machine Learning Engine</a>
                </nav>
            </div>
            
            <button type="submit" class="w-full px-4 py-3 bg-blue-600 text-white font-medium rounded-xl hover:bg-blue-700 transition shadow-sm flex items-center justify-center gap-2">
                <i class="ti ti-device-floppy"></i> Save All Settings
            </button>
        </div>

        <!-- Right Content: Setting Groups -->
        <div class="lg:col-span-2 space-y-6">

            <!-- General Settings -->
            <div id="general" class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden scroll-mt-20">
                <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                    <h4 class="font-semibold text-gray-800 flex items-center gap-2">
                        <i class="ti ti-adjustments text-blue-600"></i> General Settings
                    </h4>
                </div>
                <div class="p-6 space-y-5">
                    <div>
                        <label for="school_name" class="block text-sm font-medium text-gray-700 mb-1">School Name</label>
                        <input type="text" name="school_name" id="school_name" 
                            value="{{ $settings->get('school_name')->value ?? 'Misamis University' }}"
                            class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm">
                    </div>
                    
                    <div>
                        <label for="academic_year" class="block text-sm font-medium text-gray-700 mb-1">Current Academic Year</label>
                        <input type="text" name="academic_year" id="academic_year" placeholder="e.g., 2025-2026"
                            value="{{ $settings->get('academic_year')->value ?? '2025-2026' }}"
                            class="w-full md:w-1/2 rounded-lg border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm">
                    </div>
                    
                    <div>
                        <label for="current_semester" class="block text-sm font-medium text-gray-700 mb-1">Current Semester</label>
                        <select name="current_semester" id="current_semester" class="w-full md:w-1/2 rounded-lg border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm">
                            @php $sem = $settings->get('current_semester')->value ?? '1st'; @endphp
                            <option value="1st" {{ $sem == '1st' ? 'selected' : '' }}>1st Semester</option>
                            <option value="2nd" {{ $sem == '2nd' ? 'selected' : '' }}>2nd Semester</option>
                            <option value="Summer" {{ $sem == 'Summer' ? 'selected' : '' }}>Summer</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="semester_start_date" class="block text-sm font-medium text-gray-700 mb-1">Semester Start Date</label>
                            <input type="date" name="semester_start_date" id="semester_start_date"
                                value="{{ $settings->get('semester_start_date')->value ?? '' }}"
                                class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm">
                        </div>
                        <div>
                            <label for="semester_end_date" class="block text-sm font-medium text-gray-700 mb-1">Semester End Date</label>
                            <input type="date" name="semester_end_date" id="semester_end_date"
                                value="{{ $settings->get('semester_end_date')->value ?? '' }}"
                                class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm">
                        </div>
                    </div>
                    <p class="text-xs text-gray-500">Defines the "This Semester" range used by the Analytics dashboard's date filter.</p>
                </div>
            </div>

            <!-- SMS Integration -->
            <div id="sms" class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden scroll-mt-20">
                <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                    <h4 class="font-semibold text-gray-800 flex items-center gap-2">
                        <i class="ti ti-message text-green-600"></i> SMS Integration (Semaphore)
                    </h4>
                </div>
                <div class="p-6 space-y-5">
                    <div class="flex items-center gap-3 bg-green-50 p-4 rounded-lg border border-green-100 mb-2">
                        <input type="checkbox" name="sms_enabled" id="sms_enabled" value="1" 
                            {{ ($settings->get('sms_enabled')->value ?? '1') == '1' ? 'checked' : '' }}
                            class="w-5 h-5 text-green-600 rounded border-gray-300 focus:ring-green-500">
                        <label for="sms_enabled" class="font-medium text-green-900 cursor-pointer">Enable SMS Notifications globally</label>
                    </div>

                    <div>
                        <label for="sms_api_key" class="block text-sm font-medium text-gray-700 mb-1">Semaphore API Key</label>
                        <input type="password" name="sms_api_key" id="sms_api_key" 
                            value="{{ $settings->get('sms_api_key')->value ?? '' }}" placeholder="Enter API Key here..."
                            class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm font-mono text-sm">
                        <p class="text-xs text-gray-500 mt-1">Leave blank to simulate SMS sending (saved to logs without actual API call).</p>
                    </div>
                    
                    <div>
                        <label for="sms_sender_name" class="block text-sm font-medium text-gray-700 mb-1">Sender Name (Semaphore)</label>
                        <input type="text" name="sms_sender_name" id="sms_sender_name" 
                            value="{{ $settings->get('sms_sender_name')->value ?? 'SEMAPHORE' }}"
                            class="w-full md:w-1/2 rounded-lg border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm">
                        <p class="text-xs text-gray-500 mt-1">Must be an approved Sender ID on your Semaphore account.</p>
                    </div>
                </div>
            </div>

            <!-- Early Warning System -->
            <div id="ews" class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden scroll-mt-20">
                <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                    <h4 class="font-semibold text-gray-800 flex items-center gap-2">
                        <i class="ti ti-alert-triangle text-amber-600"></i> Early Warning System Thresholds
                    </h4>
                </div>
                <div class="p-6 space-y-5">
                    
                    <div class="flex items-center gap-3 bg-amber-50 p-4 rounded-lg border border-amber-100 mb-4">
                        <input type="checkbox" name="auto_flag_high_risk" id="auto_flag_high_risk" value="1" 
                            {{ ($settings->get('auto_flag_high_risk')->value ?? '1') == '1' ? 'checked' : '' }}
                            class="w-5 h-5 text-amber-600 rounded border-gray-300 focus:ring-amber-500">
                        <div class="cursor-pointer">
                            <label for="auto_flag_high_risk" class="font-medium text-amber-900 block">Auto-Flag High Risk Students</label>
                            <span class="text-xs text-amber-700">If enabled, the predictive engine automatically escalates matching profiles to High Risk</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label for="absence_warning_threshold" class="block text-sm font-medium text-gray-700 mb-1">Absence Warning Threshold</label>
                            <div class="flex items-center gap-2">
                                <input type="number" name="absence_warning_threshold" id="absence_warning_threshold" min="1" max="10"
                                    value="{{ $settings->get('absence_warning_threshold')->value ?? '3' }}"
                                    class="w-20 rounded-lg border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm text-center">
                                <span class="text-sm text-gray-500">absences (Triggers initial warning)</span>
                            </div>
                        </div>

                        <div>
                            <label for="absence_critical_threshold" class="block text-sm font-medium text-gray-700 mb-1">Absence Critical Threshold</label>
                            <div class="flex items-center gap-2">
                                <input type="number" name="absence_critical_threshold" id="absence_critical_threshold" min="2" max="20"
                                    value="{{ $settings->get('absence_critical_threshold')->value ?? '5' }}"
                                    class="w-20 rounded-lg border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm text-center">
                                <span class="text-sm text-gray-500">absences (Triggers parent SMS)</span>
                            </div>
                        </div>

                        <div>
                            <label for="risk_score_high_threshold" class="block text-sm font-medium text-gray-700 mb-1">High Risk Score Threshold</label>
                            <div class="flex items-center gap-2">
                                <input type="number" name="risk_score_high_threshold" id="risk_score_high_threshold" min="50" max="100"
                                    value="{{ $settings->get('risk_score_high_threshold')->value ?? '75' }}"
                                    class="w-20 rounded-lg border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm text-center">
                                <span class="text-sm text-gray-500">score (0-100)</span>
                            </div>
                        </div>
                        
                        <div>
                            <label for="risk_score_moderate_threshold" class="block text-sm font-medium text-gray-700 mb-1">Moderate Risk Score Threshold</label>
                            <div class="flex items-center gap-2">
                                <input type="number" name="risk_score_moderate_threshold" id="risk_score_moderate_threshold" min="20" max="74"
                                    value="{{ $settings->get('risk_score_moderate_threshold')->value ?? '40' }}"
                                    class="w-20 rounded-lg border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm text-center">
                                <span class="text-sm text-gray-500">score (0-100)</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</form>

<!-- ML Retrain Section (Separate Form) -->
<div class="mt-8 grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-start-2 lg:col-span-2">
        <div id="ml" class="bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden transition-all duration-300 hover:shadow-hover scroll-mt-20">
            <div class="px-6 py-4 border-b border-gray-100 bg-indigo-50/50 flex justify-between items-center">
                <h4 class="font-semibold text-indigo-800 flex items-center gap-2">
                    <i class="ti ti-brain text-indigo-600"></i> AI Machine Learning Engine
                </h4>
                <span class="px-2 py-1 bg-indigo-100 text-indigo-700 text-xs font-semibold rounded">NLP Enabled</span>
            </div>
            <div class="p-6 space-y-4">
                <p class="text-sm text-gray-600">
                    The predictive engine uses Random Forest and Natural Language Processing (NLP) to predict student risk from a student's referral history and the referral narrative. The active model was trained and validated offline (78.5% held-out accuracy).
                </p>

                @if(config('services.ml.retrain_enabled'))
                    {{-- In-app retraining explicitly re-enabled via ML_RETRAIN_ENABLED. --}}
                    <form id="retrain-ai-form" action="{{ route('admin.ml.retrain') }}" method="POST">
                        @csrf
                        <button type="button" @click="$dispatch('open-confirm-modal', {
                                formId: 'retrain-ai-form',
                                title: 'Retrain AI Model',
                                message: 'Are you sure you want to retrain the AI model? This process will read historical data and rebuild the algorithm in the background.',
                                confirmText: 'Yes, Retrain Now',
                                buttonClass: 'bg-indigo-600 hover:bg-indigo-700 shadow-indigo-200',
                                iconClass: 'ti-brain text-indigo-600',
                                iconBgClass: 'bg-indigo-50'
                            })" class="bg-indigo-600 text-white px-5 py-2.5 rounded-xl font-semibold hover:bg-indigo-700 transition flex items-center gap-2">
                            <i class="ti ti-brain"></i> Retrain Now
                        </button>
                    </form>

                    <hr class="my-4 border-gray-100">

                    <h5 class="font-medium text-gray-800 mb-2 flex items-center gap-2">
                        <i class="ti ti-upload text-gray-500"></i> Bulk Upload Historical Data
                    </h5>
                    <form action="{{ route('admin.ml.upload-csv') }}" method="POST" enctype="multipart/form-data" class="flex items-center gap-3">
                        @csrf
                        <div class="flex-1">
                            <input type="file" name="csv_file" accept=".csv" required
                                class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 transition border border-gray-300 rounded-lg">
                        </div>
                        <button type="submit" class="px-4 py-2.5 bg-gray-800 text-white font-medium rounded-xl hover:bg-gray-900 transition shadow-sm flex items-center gap-2">
                            <i class="ti ti-file-upload"></i> Upload &amp; Train
                        </button>
                    </form>
                @else
                    {{-- Default: in-app retraining is locked to protect the validated model. --}}
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-4 flex gap-3">
                        <i class="ti ti-shield-check text-emerald-600 text-xl mt-0.5"></i>
                        <div>
                            <p class="text-sm font-semibold text-emerald-900">Model protected</p>
                            <p class="text-sm text-emerald-800/90 mt-1 leading-relaxed">
                                In-app retraining is disabled to safeguard the validated model. Retraining on live
                                data would learn from the system's own past decisions, drifting the model over time.
                                Retraining is instead done <span class="font-medium">offline and reviewed</span>
                                (<code class="bg-emerald-100 px-1 py-0.5 rounded text-emerald-800">ml_engine/train_model.py</code>),
                                then the new model files are deployed deliberately.
                            </p>
                        </div>
                    </div>
                    <button type="button" disabled
                        class="bg-gray-100 text-gray-400 px-5 py-2.5 rounded-xl font-semibold flex items-center gap-2 cursor-not-allowed border border-gray-200">
                        <i class="ti ti-lock"></i> Retraining locked
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>

<script>
    // Simple smooth scrolling and active state for the local nav
    document.addEventListener('DOMContentLoaded', () => {
        const links = document.querySelectorAll('nav a');
        
        links.forEach(link => {
            link.addEventListener('click', (e) => {
                // Update active state
                links.forEach(l => {
                    l.classList.remove('bg-blue-50', 'text-blue-700');
                    l.classList.add('text-gray-600');
                });
                link.classList.remove('text-gray-600');
                link.classList.add('bg-blue-50', 'text-blue-700');
            });
        });
    });
</script>

@endsection
