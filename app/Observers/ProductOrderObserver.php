<?php

namespace App\Observers;

use App\Models\ProductOrder;
use App\Models\Student;
use App\Services\Workflow\WorkflowEngine;
use Illuminate\Support\Facades\Log;

class ProductOrderObserver
{
    public function __construct(
        protected WorkflowEngine $workflowEngine
    ) {}

    /**
     * Handle the ProductOrder "created" event.
     */
    public function created(ProductOrder $productOrder): void
    {
        $student = $this->getStudentFromOrder($productOrder);

        if (! $student) {
            return;
        }

        $this->triggerWorkflows('order_created', $student, [
            'event' => 'order_created',
            'order_id' => $productOrder->id,
            'order_number' => $productOrder->order_number,
            'order_total' => $productOrder->total_amount,
            'order_status' => $productOrder->status,
        ]);
    }

    /**
     * Handle the ProductOrder "updated" event.
     */
    public function updated(ProductOrder $productOrder): void
    {
        $student = $this->getStudentFromOrder($productOrder);

        if (! $student) {
            return;
        }

        // Check for specific status changes
        $changes = $productOrder->getChanges();

        // Order paid trigger
        if ($this->wasJustPaid($productOrder, $changes)) {
            $this->triggerWorkflows('order_paid', $student, [
                'event' => 'order_paid',
                'order_id' => $productOrder->id,
                'order_number' => $productOrder->order_number,
                'order_total' => $productOrder->total_amount,
                'paid_time' => $productOrder->paid_time,
            ]);
        }

        // Order cancelled trigger
        if ($this->wasJustCancelled($productOrder, $changes)) {
            $this->triggerWorkflows('order_cancelled', $student, [
                'event' => 'order_cancelled',
                'order_id' => $productOrder->id,
                'order_number' => $productOrder->order_number,
                'cancel_reason' => $productOrder->cancel_reason,
            ]);
        }

        // Order shipped trigger
        if (isset($changes['shipped_at']) && $productOrder->shipped_at) {
            $this->triggerWorkflows('order_shipped', $student, [
                'event' => 'order_shipped',
                'order_id' => $productOrder->id,
                'order_number' => $productOrder->order_number,
                'shipped_at' => $productOrder->shipped_at,
            ]);
        }

        // Order delivered trigger
        if (isset($changes['delivered_at']) && $productOrder->delivered_at) {
            $this->triggerWorkflows('order_delivered', $student, [
                'event' => 'order_delivered',
                'order_id' => $productOrder->id,
                'order_number' => $productOrder->order_number,
                'delivered_at' => $productOrder->delivered_at,
            ]);
        }
    }

    /**
     * Check if order was just paid.
     */
    protected function wasJustPaid(ProductOrder $order, array $changes): bool
    {
        // Check if paid_time was just set
        if (isset($changes['paid_time']) && $order->paid_time) {
            return true;
        }

        // Check if status changed to 'paid' or similar
        if (isset($changes['status']) && in_array($order->status, ['paid', 'completed', 'processing'])) {
            return $order->isPaid();
        }

        return false;
    }

    /**
     * Check if order was just cancelled.
     */
    protected function wasJustCancelled(ProductOrder $order, array $changes): bool
    {
        return isset($changes['status']) && $order->status === 'cancelled';
    }

    /**
     * Get student from order.
     */
    protected function getStudentFromOrder(ProductOrder $order): ?Student
    {
        // First check if order has a student directly
        if ($order->student_id) {
            return $order->student;
        }

        // Try to find student by customer/user
        if ($order->customer_id) {
            return Student::where('user_id', $order->customer_id)->first();
        }

        return null;
    }

    /**
     * Trigger workflows for a specific trigger type.
     */
    protected function triggerWorkflows(string $triggerType, Student $student, array $context = []): void
    {
        try {
            $workflows = $this->workflowEngine->findWorkflowsForTrigger($triggerType);

            foreach ($workflows as $workflow) {
                Log::info("Triggering workflow {$workflow->id} for student {$student->id}", [
                    'trigger_type' => $triggerType,
                    'workflow_name' => $workflow->name,
                    'order_id' => $context['order_id'] ?? null,
                ]);

                dispatch(function () use ($workflow, $student, $context) {
                    app(WorkflowEngine::class)->enroll($workflow, $student, $context);
                })->afterResponse();
            }

        } catch (\Exception $e) {
            Log::error("Failed to trigger workflows for {$triggerType}", [
                'student_id' => $student->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
