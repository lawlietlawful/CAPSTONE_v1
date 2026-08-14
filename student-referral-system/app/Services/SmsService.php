<?php

namespace App\Services;

use App\Models\SmsLog;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    /**
     * Send an SMS via Semaphore API or simulate if API key is missing.
     *
     * @param string $recipientNumber
     * @param string $message
     * @param int|null $studentId
     * @param string $recipientName
     * @param string $recipientType
     * @param int|null $referralId
     * @return bool
     */
    public function sendSms(
        string $recipientNumber,
        string $message,
        $studentId = null,
        string $recipientName = 'N/A',
        string $recipientType = 'parent',
        $referralId = null
    ): bool {
        // Fetch from dynamic settings instead of env
        $apiKey = Setting::get('sms_api_key', '');
        $senderName = Setting::get('sms_sender_name', 'SEMAPHORE');
        $smsEnabled = Setting::get('sms_enabled', true);

        // Clean the number (Semaphore typically expects 09xxxxxxxxx or 639xxxxxxxxx)
        $cleanNumber = preg_replace('/[^0-9]/', '', $recipientNumber);

        if (!$smsEnabled) {
            Log::info("SMS sending is disabled in settings. Skipped sending to {$cleanNumber}");
            return false;
        }

        if (empty($apiKey)) {
            // Simulated Success (Development Mode)
            Log::info("SIMULATED SMS to {$cleanNumber}: {$message}");
            SmsLog::create([
                'referral_id'      => $referralId,
                'student_id'       => $studentId,
                'recipient_name'   => $recipientName,
                'recipient_number' => $cleanNumber,
                'recipient_type'   => $recipientType,
                'message'          => $message,
                'status'           => 'sent',
                'sms_provider'     => 'semaphore (simulated)',
                'sent_at'          => now(),
                'error_message'    => 'Simulated success (No API Key provided)'
            ]);
            return true;
        }

        if (empty($cleanNumber) || strlen($cleanNumber) < 10) {
            SmsLog::create([
                'referral_id'      => $referralId,
                'student_id'       => $studentId,
                'recipient_name'   => $recipientName,
                'recipient_number' => $recipientNumber,
                'recipient_type'   => $recipientType,
                'message'          => $message,
                'status'           => 'failed',
                'error_message'    => 'Invalid phone number format.'
            ]);
            return false;
        }

        // Make the API Call to Semaphore
        try {
            $response = Http::post('https://api.semaphore.co/api/v4/messages', [
                'apikey'     => $apiKey,
                'number'     => $cleanNumber,
                'message'    => $message,
                'sendername' => $senderName
            ]);

            $success = $response->successful();

            SmsLog::create([
                'referral_id'      => $referralId,
                'student_id'       => $studentId,
                'recipient_name'   => $recipientName,
                'recipient_number' => $cleanNumber,
                'recipient_type'   => $recipientType,
                'message'          => $message,
                'status'           => $success ? 'sent' : 'failed',
                'sms_provider'     => 'semaphore',
                'sent_at'          => $success ? now() : null,
                'error_message'    => $success ? null : ($response->json('message') ?? $response->body()),
            ]);

            return $success;

        } catch (\Exception $e) {
            Log::error('SMS Sending Failed: ' . $e->getMessage());

            SmsLog::create([
                'referral_id'      => $referralId,
                'student_id'       => $studentId,
                'recipient_name'   => $recipientName,
                'recipient_number' => $cleanNumber,
                'recipient_type'   => $recipientType,
                'message'          => $message,
                'status'           => 'failed',
                'sms_provider'     => 'semaphore',
                'error_message'    => $e->getMessage(),
            ]);

            return false;
        }
    }
}
