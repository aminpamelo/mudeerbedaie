<?php

namespace App\Http\Controllers\LiveHost;

use App\Http\Controllers\Controller;
use App\Models\LiveHostPlatformCommissionRate;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CommissionOverviewController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('commission/Index', [
            'hosts' => $this->buildMatrix()->values(),
            'platforms' => Platform::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'slug', 'name'])
                ->map(fn (Platform $p) => [
                    'id' => $p->id,
                    'slug' => $p->slug,
                    'name' => $p->name,
                ])
                ->values(),
        ]);
    }

    public function export(): StreamedResponse
    {
        $filename = 'commission-overview-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'host_email',
                'host_name',
                'base_salary_myr',
                'primary_platform_rate_percent',
                'per_live_rate_myr',
                'upline_email',
                'l1_percent',
                'l2_percent',
            ]);

            foreach ($this->buildMatrix() as $row) {
                fputcsv($handle, [
                    $row['email'],
                    $row['name'],
                    $this->formatNumber($row['base_salary_myr']),
                    $this->formatNumber($row['primary_platform_rate_percent']),
                    $this->formatNumber($row['per_live_rate_myr']),
                    $row['upline_email'] ?? '',
                    $this->formatNumber($row['override_rate_l1_percent']),
                    $this->formatNumber($row['override_rate_l2_percent']),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Build the overview matrix: one row per active live_host user with their
     * commission profile + primary platform rate flattened for easy rendering.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function buildMatrix(): Collection
    {
        $hosts = User::query()
            ->where('role', 'live_host')
            ->with([
                'commissionProfile.upline:id,name,email',
                'platformCommissionRates' => fn ($q) => $q->with('platform:id,slug,name'),
            ])
            ->orderBy('name')
            ->get();

        return $hosts->map(function (User $host) {
            $profile = $host->commissionProfile;
            $primaryRate = $this->primaryRate($host->platformCommissionRates);

            return [
                'id' => $host->id,
                'name' => $host->name,
                'email' => $host->email,
                'status' => $host->status,
                'base_salary_myr' => $profile ? (float) $profile->base_salary_myr : 0.0,
                'per_live_rate_myr' => $profile ? (float) $profile->per_live_rate_myr : 0.0,
                'override_rate_l1_percent' => $profile ? (float) $profile->override_rate_l1_percent : 0.0,
                'override_rate_l2_percent' => $profile ? (float) $profile->override_rate_l2_percent : 0.0,
                'upline_user_id' => $profile?->upline_user_id,
                'upline_name' => $profile?->upline?->name,
                'upline_email' => $profile?->upline?->email,
                'commission_profile_id' => $profile?->id,
                'has_profile' => $profile !== null,
                'primary_platform_rate_id' => $primaryRate?->id,
                'primary_platform_rate_percent' => $primaryRate
                    ? (float) $primaryRate->commission_rate_percent
                    : 0.0,
                'primary_platform_id' => $primaryRate?->platform_id,
                'primary_platform_slug' => $primaryRate?->platform?->slug,
                'primary_platform_name' => $primaryRate?->platform?->name,
            ];
        });
    }

    /**
     * Same heuristic as the shared-props middleware: prefer the TikTok Shop
     * rate if one exists, otherwise fall back to the first active rate.
     *
     * @param  \Illuminate\Support\Collection<int, LiveHostPlatformCommissionRate>  $rates
     */
    private function primaryRate(Collection $rates): ?LiveHostPlatformCommissionRate
    {
        return $rates->first(fn (LiveHostPlatformCommissionRate $r) => $r->platform?->slug === 'tiktok-shop')
            ?? $rates->first();
    }

    private function formatNumber(float $value): string
    {
        if ((float) (int) $value === $value) {
            return (string) (int) $value;
        }

        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
