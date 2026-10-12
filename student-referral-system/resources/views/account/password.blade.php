@extends('layouts.admin')

@section('title', 'Change Password')
@section('page-title', 'Change Password')
@section('page-sub', 'Choose a new password for your account')

@section('content')

@php
    $bag = $errors->updatePassword;
    $field = 'w-full rounded-xl border bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm';
@endphp

<div class="max-w-xl">
    @if($forced)
        <div class="mb-5 p-4 rounded-xl bg-amber-50 border border-amber-200 flex gap-3" data-password-forced>
            <i class="ti ti-key text-amber-600 text-lg mt-0.5 shrink-0"></i>
            <div>
                <p class="text-sm font-semibold text-amber-900">Your password was reset</p>
                <p class="text-xs text-amber-800 mt-0.5">You signed in with a temporary password. Choose your own to continue — the rest of the system opens once you do.</p>
            </div>
        </div>
    @endif

    <div class="bg-white border border-gray-100 rounded-2xl shadow-premium">
        <div class="px-6 py-5 border-b border-gray-100 bg-gray-50/50 rounded-t-2xl">
            <h3 class="font-semibold text-gray-800 flex items-center gap-2"><i class="ti ti-lock text-blue-600"></i> {{ auth()->user()->name }}</h3>
            <p class="text-xs text-gray-500 mt-0.5">{{ auth()->user()->email }}</p>
        </div>

        <form method="POST" action="{{ route('password.update') }}" class="p-6 space-y-5">
            @csrf
            @method('PUT')

            <div>
                <label for="current_password" class="block text-sm font-medium text-gray-700 mb-1">{{ $forced ? 'Temporary password' : 'Current password' }}</label>
                <input type="password" id="current_password" name="current_password" required autocomplete="current-password"
                       class="{{ $field }} {{ $bag->has('current_password') ? 'border-red-500' : 'border-gray-300' }}">
                @if($bag->has('current_password')) <p class="mt-1 text-sm text-red-500">{{ $bag->first('current_password') }}</p> @endif
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">New password</label>
                <input type="password" id="password" name="password" required autocomplete="new-password" minlength="8"
                       class="{{ $field }} {{ $bag->has('password') ? 'border-red-500' : 'border-gray-300' }}">
                @if($bag->has('password'))
                    @foreach($bag->get('password') as $message)
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @endforeach
                @else
                    <p class="mt-1 text-xs text-gray-400">At least 8 characters, with letters and numbers.</p>
                @endif
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">Confirm new password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password"
                       class="{{ $field }} border-gray-300">
            </div>

            <div class="pt-2 flex items-center justify-between gap-3">
                <p class="text-xs text-gray-400 flex items-center gap-1.5"><i class="ti ti-devices"></i> Other signed-in sessions will be signed out.</p>
                <button type="submit" class="px-5 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition shadow-sm flex items-center gap-2">
                    <i class="ti ti-device-floppy"></i> Change Password
                </button>
            </div>
        </form>
    </div>
</div>

@endsection
