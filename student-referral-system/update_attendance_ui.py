import re

filepath = r'd:\CAPSTONE PROJECT\student-referral-system\resources\views\admin\attendance\index.blade.php'
with open(filepath, 'r', encoding='utf-8') as f:
    text = f.read()

# 1. Update Date Picker & Stats (lines 12-62 roughly)
stats_replacement = """    {{-- Date Picker Card --}}
    <div class="bg-white border border-gray-100 rounded-2xl shadow-premium p-4 flex flex-col justify-center">
        <form method="GET" action="{{ route('admin.attendance.index') }}" id="dateForm">
            <label class="block text-xs font-medium text-gray-500 mb-1">Date Range</label>
            <div class="flex gap-2">
                <input type="date" name="start_date" value="{{ $startDate }}"
                    onchange="document.getElementById('dateForm').submit()"
                    class="w-full rounded-lg border-gray-300 text-[11px] font-medium focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm p-1.5">
                <input type="date" name="end_date" value="{{ $endDate }}"
                    onchange="document.getElementById('dateForm').submit()"
                    class="w-full rounded-lg border-gray-300 text-[11px] font-medium focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm p-1.5">
            </div>
            {{-- Preserve other filters --}}
            @if(request('course')) <input type="hidden" name="course" value="{{ request('course') }}"> @endif
            @if(request('section')) <input type="hidden" name="section" value="{{ request('section') }}"> @endif
            @if(request('status')) <input type="hidden" name="status" value="{{ request('status') }}"> @endif
            @if(request('search')) <input type="hidden" name="search" value="{{ request('search') }}"> @endif
        </form>
        <p class="text-[10px] text-gray-400 mt-2 uppercase tracking-wide">{{ \\Carbon\\Carbon::parse($startDate)->format('M d') }} - {{ \\Carbon\\Carbon::parse($endDate)->format('M d, Y') }}</p>
    </div>

    {{-- Present --}}
    <div class="bg-gradient-to-br from-green-50 to-emerald-100/50 border border-green-100/50 rounded-2xl shadow-premium p-4 flex flex-col gap-2 hover:shadow-md transition">
        <div class="w-10 h-10 rounded-xl bg-white shadow-sm flex items-center justify-center text-green-600">
            <i class="ti ti-user-check text-xl"></i>
        </div>
        <div class="text-3xl font-bold text-green-700 leading-none">{{ number_format($presentCount) }}</div>
        <div class="text-[10px] font-bold text-green-600/70 uppercase tracking-wider">Present</div>
    </div>

    {{-- Absent --}}
    <div class="bg-gradient-to-br from-red-50 to-rose-100/50 border border-red-100/50 rounded-2xl shadow-premium p-4 flex flex-col gap-2 hover:shadow-md transition">
        <div class="w-10 h-10 rounded-xl bg-white shadow-sm flex items-center justify-center text-red-600">
            <i class="ti ti-user-x text-xl"></i>
        </div>
        <div class="text-3xl font-bold text-red-700 leading-none">{{ number_format($absentCount) }}</div>
        <div class="text-[10px] font-bold text-red-600/70 uppercase tracking-wider">Absent</div>
    </div>

    {{-- Late --}}
    <div class="bg-gradient-to-br from-amber-50 to-yellow-100/50 border border-amber-100/50 rounded-2xl shadow-premium p-4 flex flex-col gap-2 hover:shadow-md transition">
        <div class="w-10 h-10 rounded-xl bg-white shadow-sm flex items-center justify-center text-amber-600">
            <i class="ti ti-clock-exclamation text-xl"></i>
        </div>
        <div class="text-3xl font-bold text-amber-700 leading-none">{{ number_format($lateCount) }}</div>
        <div class="text-[10px] font-bold text-amber-600/70 uppercase tracking-wider">Late</div>
    </div>

    {{-- Excused --}}
    <div class="bg-gradient-to-br from-blue-50 to-indigo-100/50 border border-blue-100/50 rounded-2xl shadow-premium p-4 flex flex-col gap-2 hover:shadow-md transition">
        <div class="w-10 h-10 rounded-xl bg-white shadow-sm flex items-center justify-center text-blue-600">
            <i class="ti ti-clipboard-check text-xl"></i>
        </div>
        <div class="text-3xl font-bold text-blue-700 leading-none">{{ number_format($excusedCount) }}</div>
        <div class="text-[10px] font-bold text-blue-600/70 uppercase tracking-wider">Excused</div>
    </div>"""
text = re.sub(r'\{\{-- Date Picker Card --\}\}.*?\{\{-- Excused --\}\}.*?</div>\s*</div>', stats_replacement, text, flags=re.DOTALL)

# 2. Update Form inputs and add Export button
form_top = """            <form method="GET" action="{{ route('admin.attendance.index') }}" class="flex flex-wrap items-end gap-3">
                <input type="hidden" name="start_date" value="{{ $startDate }}">
                <input type="hidden" name="end_date" value="{{ $endDate }}">"""
text = re.sub(r'<form method="GET" action="\{\{\s*route\(\'admin.attendance.index\'\)\s*\}\}" class="flex flex-wrap items-end gap-3">\s*<input type="hidden" name="date" value="\{\{\s*\$date\s*\}\}">', form_top, text)

form_bottom = """                <div class="flex gap-2 w-full lg:w-auto mt-2 lg:mt-0 ml-auto">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition shadow-sm flex items-center gap-1.5">
                        <i class="ti ti-filter"></i> Filter
                    </button>
                    <a href="{{ route('admin.attendance.index', ['start_date' => $startDate, 'end_date' => $endDate]) }}" class="px-4 py-2 bg-white border border-gray-300 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition flex items-center gap-1.5">
                        <i class="ti ti-x"></i> Clear
                    </a>
                    <a href="{{ route('admin.attendance.export', request()->query()) }}" class="px-4 py-2 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700 transition shadow-sm flex items-center gap-1.5 ml-2">
                        <i class="ti ti-download"></i> Export CSV
                    </a>
                </div>"""
text = re.sub(r'<div class="flex gap-2">\s*<button type="submit".*?<i class="ti ti-filter"></i> Filter\s*</button>\s*<a href="\{\{\s*route\(\'admin.attendance.index\', \[\'date\' => \$date\]\)\s*\}\}".*?<i class="ti ti-x"></i> Clear\s*</a>\s*</div>', form_bottom, text, flags=re.DOTALL)

# 3. Update Empty State
empty_state = """                                <td colspan="5" class="py-16 text-center">
                                    <div class="flex flex-col items-center">
                                        <div class="w-24 h-24 mb-4 opacity-50">
                                            <svg class="w-full h-full text-blue-200" fill="currentColor" viewBox="0 0 24 24"><path d="M19,4H17V3a1,1,0,0,0-2,0V4H9V3A1,1,0,0,0,7,3V4H5A3,3,0,0,0,2,7V19a3,3,0,0,0,3,3H19a3,3,0,0,0,3-3V7A3,3,0,0,0,19,4Zm1,15a1,1,0,0,1-1,1H5a1,1,0,0,1-1-1V10H20ZM20,8H4V7A1,1,0,0,1,5,6H7V7A1,1,0,0,0,9,7V6h6V7a1,1,0,0,0,2,0V6h2a1,1,0,0,1,1,1Z"/></svg>
                                        </div>
                                        <h3 class="text-lg font-bold text-gray-900 mb-1">No Attendance Found</h3>
                                        <p class="text-sm text-gray-500 max-w-sm mx-auto">It looks like there are no attendance records matching your criteria for the selected date range.</p>
                                    </div>
                                </td>"""
text = re.sub(r'<td colspan="5" class="py-16 text-center">.*?</td>', empty_state, text, flags=re.DOTALL)

with open(filepath, 'w', encoding='utf-8') as f:
    f.write(text)
print("UI script updated!")
