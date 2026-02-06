<?php

namespace App\Services\Workflow\Actions;

use App\Models\Student;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookHandler implements ActionHandlerInterface
{
    public function execute(Student $student, array $config): array
    {
        $url = $config['url'] ?? null;
        $method = strtoupper($config['method'] ?? 'POST');
        $headers = $config['headers'] ?? [];
        $includeStudentData = $config['include_student_data'] ?? true;
        $customPayload = $config['payload'] ?? [];

        if (! $url) {
            return [
                'success' => false,
                'message' => 'Webhook URL is required',
            ];
        }

        // Validate URL
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return [
                'success' => false,
                'message' => 'Invalid webhook URL',
            ];
        }

        try {
            // Build payload
            $payload = $customPayload;

            if ($includeStudentData) {
                $payload['student'] = [
                    'id' => $student->id,
                    'student_id' => $student->student_id,
                    'name' => $student->name,
                    'email' => $student->email,
                    'phone' => $student->phone,
                ];
            }

            $payload['timestamp'] = now()->toIso8601String();
            $payload['source'] = 'workflow';

            // Build HTTP request
            $request = Http::timeout(30);

            // Add headers
            if (! empty($headers)) {
                $request = $request->withHeaders($headers);
            }

            // Send request based on method
            $response = match ($method) {
                'GET' => $request->get($url, $payload),
                'PUT' => $request->put($url, $payload),
                'PATCH' => $request->patch($url, $payload),
                'DELETE' => $request->delete($url, $payload),
                default => $request->post($url, $payload),
            };

            $statusCode = $response->status();
            $isSuccessful = $response->successful();

            Log::info("Webhook sent for student {$student->id}", [
                'url' => $url,
                'method' => $method,
                'status' => $statusCode,
                'successful' => $isSuccessful,
            ]);

            if (! $isSuccessful) {
                return [
                    'success' => false,
                    'message' => "Webhook returned status {$statusCode}",
                    'data' => [
                        'status_code' => $statusCode,
                        'response' => $response->body(),
                    ],
                ];
            }

            return [
                'success' => true,
                'message' => 'Webhook sent successfully',
                'data' => [
                    'status_code' => $statusCode,
                    'response' => $response->json() ?? $response->body(),
                ],
            ];

        } catch (\Exception $e) {
            Log::error("Webhook failed for student {$student->id}", [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Webhook failed: '.$e->getMessage(),
            ];
        }
    }
}
