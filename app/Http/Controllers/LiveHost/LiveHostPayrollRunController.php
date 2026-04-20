<?php

namespace App\Http\Controllers\LiveHost;

use App\Exceptions\LiveHost\PayrollRunStateException;
use App\Http\Controllers\Controller;
use App\Http\Requests\LiveHost\StoreLiveHostPayrollRunRequest;
use App\Models\LiveHostPayrollItem;
use App\Models\LiveHostPayrollRun;
use App\Services\LiveHost\LiveHostPayrollService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * HTTP layer for Live Host payroll runs. Generates, shows, recomputes, locks,
 * marks-paid, and exports (CSV) runs via LiveHostPayrollService. Lifecycle
 * transitions that violate state machine invariants bubble up as
 * PayrollRunStateException and are converted to friendly flash messages.
 */
class LiveHostPayrollRunController extends Controller
{
    public function __construct(private LiveHostPayrollService $service) {}

    public function index(): Response
    {
        $runs = LiveHostPayrollRun::query()
            ->withCount('items')
            ->withSum('items as net_payout_total_myr', 'net_payout_myr')
            ->withSum('items as gross_total_myr', 'gross_total_myr')
            ->orderByDesc('period_start')
            ->orderByDesc('id')
            ->paginate(20)
            ->through(fn (LiveHostPayrollRun $run) => [
                'id' => $run->id,
                'period_start' => $run->period_start?->toDateString(),
                'period_end' => $run->period_end?->toDateString(),
                'cutoff_date' => $run->cutoff_date?->toDateString(),
                'status' => $run->status,
                'items_count' => (int) $run->items_count,
                'net_payout_total_myr' => (float) ($run->net_payout_total_myr ?? 0),
                'gross_total_myr' => (float) ($run->gross_total_myr ?? 0),
                'locked_at' => $run->locked_at?->toIso8601String(),
                'paid_at' => $run->paid_at?->toIso8601String(),
            ]);

        return Inertia::render('payroll/Index', [
            'runs' => $runs,
        ]);
    }

    public function store(StoreLiveHostPayrollRunRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $run = $this->service->generateDraft(
            Carbon::parse($validated['period_start'])->startOfDay(),
            Carbon::parse($validated['period_end'])->endOfDay(),
            $request->user(),
        );

        return redirect()
            ->route('livehost.payroll.show', $run)
            ->with('success', 'Payroll run generated as draft.');
    }

    public function show(LiveHostPayrollRun $run): Response
    {
        $run->load([
            'items.user:id,name,email',
            'lockedBy:id,name,email',
        ]);

        $items = $run->items
            ->sortBy(fn (LiveHostPayrollItem $item) => mb_strtolower((string) $item->user?->name))
            ->values()
            ->map(fn (LiveHostPayrollItem $item) => $this->shapeItem($item));

        return Inertia::render('payroll/Show', [
            'run' => [
                'id' => $run->id,
                'period_start' => $run->period_start?->toDateString(),
                'period_end' => $run->period_end?->toDateString(),
                'cutoff_date' => $run->cutoff_date?->toDateString(),
                'status' => $run->status,
                'locked_at' => $run->locked_at?->toIso8601String(),
                'locked_by' => $run->lockedBy?->only(['id', 'name', 'email']),
                'paid_at' => $run->paid_at?->toIso8601String(),
                'notes' => $run->notes,
                'items' => $items,
                'totals' => [
                    'base_salary_myr' => (float) $run->items->sum('base_salary_myr'),
                    'total_per_live_myr' => (float) $run->items->sum('total_per_live_myr'),
                    'net_gmv_myr' => (float) $run->items->sum('net_gmv_myr'),
                    'gmv_commission_myr' => (float) $run->items->sum('gmv_commission_myr'),
                    'override_l1_myr' => (float) $run->items->sum('override_l1_myr'),
                    'override_l2_myr' => (float) $run->items->sum('override_l2_myr'),
                    'gross_total_myr' => (float) $run->items->sum('gross_total_myr'),
                    'deductions_myr' => (float) $run->items->sum('deductions_myr'),
                    'net_payout_myr' => (float) $run->items->sum('net_payout_myr'),
                ],
            ],
        ]);
    }

    public function recompute(LiveHostPayrollRun $run): RedirectResponse
    {
        try {
            $this->service->recompute($run);
        } catch (PayrollRunStateException $e) {
            return redirect()
                ->route('livehost.payroll.show', $run)
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('livehost.payroll.show', $run)
            ->with('success', 'Payroll run recomputed.');
    }

    public function lock(LiveHostPayrollRun $run): RedirectResponse
    {
        try {
            $this->service->lock($run, request()->user());
        } catch (PayrollRunStateException $e) {
            return redirect()
                ->route('livehost.payroll.show', $run)
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('livehost.payroll.show', $run)
            ->with('success', 'Payroll run locked.');
    }

    public function markPaid(LiveHostPayrollRun $run): RedirectResponse
    {
        try {
            $this->service->markPaid($run, request()->user());
        } catch (PayrollRunStateException $e) {
            return redirect()
                ->route('livehost.payroll.show', $run)
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('livehost.payroll.show', $run)
            ->with('success', 'Payroll run marked as paid.');
    }

    public function export(LiveHostPayrollRun $run): StreamedResponse
    {
        $run->load('items.user:id,name,email');

        $filename = sprintf(
            'payroll-%s-%s.csv',
            $run->period_start?->toDateString() ?? 'unknown',
            $run->period_end?->toDateString() ?? 'unknown',
        );

        $items = $run->items
            ->sortBy(fn (LiveHostPayrollItem $item) => mb_strtolower((string) $item->user?->name))
            ->values();

        return response()->streamDownload(function () use ($items) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Host',
                'Base Salary',
                'Sessions',
                'Per-Live Total',
                'Gross GMV',
                'Adjustments',
                'Net GMV',
                'GMV Commission',
                'Override L1',
                'Override L2',
                'Gross Total',
                'Deductions',
                'Net Payout',
            ]);

            foreach ($items as $item) {
                fputcsv($handle, [
                    $item->user?->name ?? '',
                    $this->formatMoney($item->base_salary_myr),
                    (int) $item->sessions_count,
                    $this->formatMoney($item->total_per_live_myr),
                    $this->formatMoney($item->total_gmv_myr),
                    $this->formatMoney($item->total_gmv_adjustment_myr),
                    $this->formatMoney($item->net_gmv_myr),
                    $this->formatMoney($item->gmv_commission_myr),
                    $this->formatMoney($item->override_l1_myr),
                    $this->formatMoney($item->override_l2_myr),
                    $this->formatMoney($item->gross_total_myr),
                    $this->formatMoney($item->deductions_myr),
                    $this->formatMoney($item->net_payout_myr),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Shape a payroll item for the Show page, inlining host identity and the
     * breakdown JSON so the React UI doesn't need a second fetch.
     *
     * @return array<string, mixed>
     */
    private function shapeItem(LiveHostPayrollItem $item): array
    {
        return [
            'id' => $item->id,
            'user_id' => $item->user_id,
            'host_name' => $item->user?->name,
            'host_email' => $item->user?->email,
            'base_salary_myr' => (float) $item->base_salary_myr,
            'sessions_count' => (int) $item->sessions_count,
            'total_per_live_myr' => (float) $item->total_per_live_myr,
            'total_gmv_myr' => (float) $item->total_gmv_myr,
            'total_gmv_adjustment_myr' => (float) $item->total_gmv_adjustment_myr,
            'net_gmv_myr' => (float) $item->net_gmv_myr,
            'gmv_commission_myr' => (float) $item->gmv_commission_myr,
            'override_l1_myr' => (float) $item->override_l1_myr,
            'override_l2_myr' => (float) $item->override_l2_myr,
            'gross_total_myr' => (float) $item->gross_total_myr,
            'deductions_myr' => (float) $item->deductions_myr,
            'net_payout_myr' => (float) $item->net_payout_myr,
            'calculation_breakdown_json' => $item->calculation_breakdown_json,
        ];
    }

    private function formatMoney(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
