<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seminar Check-in</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
</head>
<body class="bg-gray-50 flex items-center justify-center min-h-screen p-4">
    <div class="bg-white rounded-2xl shadow-xl p-8 max-w-md w-full text-center">
        @if($success)
            <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="ti ti-check text-4xl text-green-600"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Check-in Successful!</h2>
            <p class="text-gray-600 mb-6">{{ $message }}</p>
        @else
            <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="ti ti-x text-4xl text-red-600"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Check-in Failed</h2>
            <p class="text-gray-600 mb-6">{{ $message }}</p>
        @endif

        <div class="bg-gray-50 rounded-xl p-4 text-left border border-gray-100 mb-6">
            <h4 class="font-semibold text-gray-800 text-sm mb-1">Seminar Details:</h4>
            <p class="text-gray-900 font-medium">{{ $seminar->title }}</p>
            <p class="text-xs text-gray-500 mt-1"><i class="ti ti-calendar"></i> {{ \Carbon\Carbon::parse($seminar->date)->format('M d, Y') }} at {{ \Carbon\Carbon::parse($seminar->time)->format('h:i A') }}</p>
            <p class="text-xs text-gray-500 mt-0.5"><i class="ti ti-map-pin"></i> {{ $seminar->venue }}</p>
        </div>

        <a href="{{ route('student.dashboard') }}" class="inline-block w-full px-6 py-3 bg-blue-600 text-white font-semibold rounded-xl hover:bg-blue-700 transition shadow-sm">
            Go to Dashboard
        </a>
    </div>
</body>
</html>
