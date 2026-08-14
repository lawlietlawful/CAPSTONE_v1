@extends('layouts.teacher')

@section('title', 'Teacher Dashboard')
@section('page-title', 'Dashboard Overview')
@section('page-sub', 'Monitor students and submit referrals to the Guidance Office')

@section('content')

{{-- ── Quick Stats ───────────────────────────────────────────── --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    
    <div class="bg-white rounded-2xl p-6 shadow-premium border border-gray-100 flex items-center gap-4 transition-all duration-300 hover:-translate-y-1 hover:shadow-hover">
        <div class="w-14 h-14 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-3xl transition-transform group-hover:scale-110">
            <i class="ti ti-users"></i>
        </div>
        <div>
            <div class="text-sm font-semibold text-gray-400 uppercase tracking-wider">Total Students</div>
            <div class="text-3xl font-bold text-gray-800">{{ $totalStudents }}</div>
        </div>
    </div>

    <div class="bg-white rounded-2xl p-6 shadow-premium border border-gray-100 flex items-center gap-4 transition-all duration-300 hover:-translate-y-1 hover:shadow-hover">
        <div class="w-14 h-14 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-3xl transition-transform group-hover:scale-110">
            <i class="ti ti-file-export"></i>
        </div>
        <div>
            <div class="text-sm font-semibold text-gray-400 uppercase tracking-wider">My Referrals</div>
            <div class="text-3xl font-bold text-gray-800">{{ $myReferrals }}</div>
        </div>
    </div>

    <div class="bg-white rounded-2xl p-6 shadow-premium border border-gray-100 flex items-center gap-4 transition-all duration-300 hover:-translate-y-1 hover:shadow-hover">
        <div class="w-14 h-14 rounded-xl bg-orange-50 text-orange-600 flex items-center justify-center text-3xl transition-transform group-hover:scale-110">
            <i class="ti ti-clock"></i>
        </div>
        <div>
            <div class="text-sm font-semibold text-gray-400 uppercase tracking-wider">Pending Review</div>
            <div class="text-3xl font-bold text-gray-800">{{ $pendingReferrals }}</div>
        </div>
    </div>

</div>

{{-- ── Two Column Layout ────────────────────────────────────────── --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    {{-- Left: Students I Referred --}}
    <div class="lg:col-span-2 space-y-6">
        
        <div class="bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden">
            <div class="px-6 py-4 bg-indigo-50/50 border-b border-indigo-100 flex items-center justify-between">
                <h3 class="font-bold text-indigo-800 flex items-center gap-2">
                    <i class="ti ti-file-export text-xl"></i> Students I Referred
                </h3>
                <a href="{{ route('teacher.referrals.index') }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition">
                    View All →
                </a>
            </div>
            
            <div class="p-0">
                @if($recentReferrals->count() > 0)
                    <div class="divide-y divide-gray-100">
                        @foreach($recentReferrals as $referral)
                            <div class="p-5 hover:bg-gray-50 transition flex items-center justify-between gap-4">
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center font-bold">
                                        {{ strtoupper(substr($referral->student->first_name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <h4 class="font-semibold text-gray-900">{{ $referral->student->first_name }} {{ $referral->student->last_name }}</h4>
                                        <div class="text-xs text-gray-500 flex items-center gap-2 mt-0.5">
                                            <span>ID: {{ $referral->student->student_id_number }}</span>
                                            <span class="w-1 h-1 rounded-full bg-gray-300"></span>
                                            @php
                                                $concernClass = match($referral->concern_type) {
                                                    'academic'      => 'bg-blue-100 text-blue-700',
                                                    'behavioral'    => 'bg-red-100 text-red-700',
                                                    'emotional'     => 'bg-purple-100 text-purple-700',
                                                    'family'        => 'bg-amber-100 text-amber-700',
                                                    'peer_conflict' => 'bg-orange-100 text-orange-700',
                                                    'attendance'    => 'bg-gray-100 text-gray-700',
                                                    default         => 'bg-gray-100 text-gray-700',
                                                };
                                            @endphp
                                            <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-medium {{ $concernClass }}">
                                                {{ ucfirst(str_replace('_', ' ', $referral->concern_type ?? 'other')) }}
                                            </span>
                                            <span class="w-1 h-1 rounded-full bg-gray-300"></span>
                                            <span>{{ $referral->created_at->format('M d, Y') }}</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div>
                                    @php
                                        $statusBadge = match($referral->status) {
                                            'pending'     => 'bg-orange-50 text-orange-700 border-orange-200',
                                            'in_progress' => 'bg-blue-50 text-blue-700 border-blue-200',
                                            'resolved'    => 'bg-green-50 text-green-700 border-green-200',
                                            default       => 'bg-gray-100 text-gray-500 border-gray-200',
                                        };
                                        $statusLabel = match($referral->status) {
                                            'pending'     => 'Pending Review',
                                            'in_progress' => 'In Progress',
                                            'resolved'    => 'Resolved',
                                            default       => ucfirst($referral->status),
                                        };
                                    @endphp
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium border {{ $statusBadge }}">
                                        {{ $statusLabel }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="p-8 text-center">
                        <div class="w-16 h-16 bg-gray-50 text-gray-400 rounded-full flex items-center justify-center text-3xl mx-auto mb-3">
                            <i class="ti ti-file-off"></i>
                        </div>
                        <h4 class="font-semibold text-gray-900 mb-1">No referrals yet</h4>
                        <p class="text-sm text-gray-500">You haven't filed any referrals. Use the Quick Actions to get started.</p>
                    </div>
                @endif
            </div>
        </div>

    </div>

    {{-- Right: Quick Actions --}}
    <div class="lg:col-span-1 space-y-6">
        
        <div class="bg-white rounded-2xl shadow-premium border border-gray-100 p-6">
            <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
                <i class="ti ti-bolt text-amber-500"></i> Quick Actions
            </h3>
            
            <div class="space-y-3">
                <a href="{{ route('teacher.referrals.create') }}" class="flex items-center gap-4 p-4 rounded-xl border border-gray-100 hover:border-indigo-200 hover:bg-indigo-50/50 transition group">
                    <div class="w-10 h-10 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center group-hover:bg-indigo-600 group-hover:text-white transition">
                        <i class="ti ti-file-export text-xl"></i>
                    </div>
                    <div>
                        <div class="font-semibold text-gray-800 text-sm">New Referral</div>
                        <div class="text-xs text-gray-500">Refer a student to guidance</div>
                    </div>
                </a>
                
                <a href="{{ route('teacher.behavioral-reports.create') }}" class="flex items-center gap-4 p-4 rounded-xl border border-gray-100 hover:border-amber-200 hover:bg-amber-50/50 transition group">
                    <div class="w-10 h-10 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center group-hover:bg-amber-500 group-hover:text-white transition">
                        <i class="ti ti-report text-xl"></i>
                    </div>
                    <div>
                        <div class="font-semibold text-gray-800 text-sm">Log Incident</div>
                        <div class="text-xs text-gray-500">File a behavioral report</div>
                    </div>
                </a>
            </div>
        </div>
        
    </div>
</div>

@endsection
