<?php

namespace App\Services\CRM;

use App\Models\ContactActivity;
use App\Models\Student;
use Illuminate\Database\Eloquent\Collection;

class ContactActivityService
{
    // Activity types
    public const TYPE_PAGE_VIEW = 'page_view';

    public const TYPE_EMAIL_OPENED = 'email_opened';

    public const TYPE_EMAIL_CLICKED = 'email_clicked';

    public const TYPE_WHATSAPP_SENT = 'whatsapp_sent';

    public const TYPE_WHATSAPP_REPLIED = 'whatsapp_replied';

    public const TYPE_ORDER_CREATED = 'order_created';

    public const TYPE_ORDER_PAID = 'order_paid';

    public const TYPE_ORDER_CANCELLED = 'order_cancelled';

    public const TYPE_ENROLLMENT_CREATED = 'enrollment_created';

    public const TYPE_CLASS_ATTENDED = 'class_attended';

    public const TYPE_CLASS_ABSENT = 'class_absent';

    public const TYPE_TAG_ADDED = 'tag_added';

    public const TYPE_TAG_REMOVED = 'tag_removed';

    public const TYPE_WORKFLOW_ENTERED = 'workflow_entered';

    public const TYPE_WORKFLOW_COMPLETED = 'workflow_completed';

    public const TYPE_WORKFLOW_EXITED = 'workflow_exited';

    public const TYPE_NOTE_ADDED = 'note_added';

    public const TYPE_PROFILE_UPDATED = 'profile_updated';

    public const TYPE_LOGIN = 'login';

    public const TYPE_CUSTOM = 'custom';

    public function log(
        Student $student,
        string $type,
        string $title,
        ?string $description = null,
        array $metadata = [],
        ?int $performedBy = null
    ): ContactActivity {
        return ContactActivity::create([
            'student_id' => $student->id,
            'type' => $type,
            'title' => $title,
            'description' => $description,
            'metadata' => $metadata,
            'performed_by' => $performedBy ?? auth()->id(),
        ]);
    }

    public function logPageView(Student $student, string $pageUrl, ?string $pageTitle = null): ContactActivity
    {
        return $this->log(
            $student,
            self::TYPE_PAGE_VIEW,
            $pageTitle ?? 'Viewed a page',
            "Visited: {$pageUrl}",
            ['url' => $pageUrl, 'title' => $pageTitle]
        );
    }

    public function logEmailOpened(Student $student, string $emailSubject, ?int $communicationLogId = null): ContactActivity
    {
        return $this->log(
            $student,
            self::TYPE_EMAIL_OPENED,
            'Opened email',
            "Subject: {$emailSubject}",
            ['subject' => $emailSubject, 'communication_log_id' => $communicationLogId]
        );
    }

    public function logEmailClicked(Student $student, string $emailSubject, string $linkUrl): ContactActivity
    {
        return $this->log(
            $student,
            self::TYPE_EMAIL_CLICKED,
            'Clicked email link',
            "Subject: {$emailSubject}, Link: {$linkUrl}",
            ['subject' => $emailSubject, 'link_url' => $linkUrl]
        );
    }

    public function logWhatsAppSent(Student $student, string $message, ?int $communicationLogId = null): ContactActivity
    {
        return $this->log(
            $student,
            self::TYPE_WHATSAPP_SENT,
            'WhatsApp message sent',
            substr($message, 0, 100).(strlen($message) > 100 ? '...' : ''),
            ['message' => $message, 'communication_log_id' => $communicationLogId]
        );
    }

    public function logWhatsAppReplied(Student $student, string $message): ContactActivity
    {
        return $this->log(
            $student,
            self::TYPE_WHATSAPP_REPLIED,
            'Replied to WhatsApp',
            substr($message, 0, 100).(strlen($message) > 100 ? '...' : ''),
            ['message' => $message]
        );
    }

    public function logOrderCreated(Student $student, string $orderNumber, float $amount): ContactActivity
    {
        return $this->log(
            $student,
            self::TYPE_ORDER_CREATED,
            'Created order',
            "Order #{$orderNumber} - RM ".number_format($amount, 2),
            ['order_number' => $orderNumber, 'amount' => $amount]
        );
    }

    public function logOrderPaid(Student $student, string $orderNumber, float $amount): ContactActivity
    {
        return $this->log(
            $student,
            self::TYPE_ORDER_PAID,
            'Completed payment',
            "Order #{$orderNumber} - RM ".number_format($amount, 2),
            ['order_number' => $orderNumber, 'amount' => $amount]
        );
    }

    public function logOrderCancelled(Student $student, string $orderNumber, ?string $reason = null): ContactActivity
    {
        return $this->log(
            $student,
            self::TYPE_ORDER_CANCELLED,
            'Order cancelled',
            "Order #{$orderNumber}".($reason ? " - Reason: {$reason}" : ''),
            ['order_number' => $orderNumber, 'reason' => $reason]
        );
    }

    public function logEnrollmentCreated(Student $student, string $className): ContactActivity
    {
        return $this->log(
            $student,
            self::TYPE_ENROLLMENT_CREATED,
            'Enrolled in class',
            "Enrolled in: {$className}",
            ['class_name' => $className]
        );
    }

    public function logClassAttended(Student $student, string $className, string $sessionDate): ContactActivity
    {
        return $this->log(
            $student,
            self::TYPE_CLASS_ATTENDED,
            'Attended class',
            "{$className} - {$sessionDate}",
            ['class_name' => $className, 'session_date' => $sessionDate]
        );
    }

    public function logClassAbsent(Student $student, string $className, string $sessionDate): ContactActivity
    {
        return $this->log(
            $student,
            self::TYPE_CLASS_ABSENT,
            'Absent from class',
            "{$className} - {$sessionDate}",
            ['class_name' => $className, 'session_date' => $sessionDate]
        );
    }

    public function logTagAdded(Student $student, string $tagName, ?string $source = null): ContactActivity
    {
        return $this->log(
            $student,
            self::TYPE_TAG_ADDED,
            'Tag added',
            "Tag: {$tagName}".($source ? " (Source: {$source})" : ''),
            ['tag_name' => $tagName, 'source' => $source]
        );
    }

    public function logTagRemoved(Student $student, string $tagName): ContactActivity
    {
        return $this->log(
            $student,
            self::TYPE_TAG_REMOVED,
            'Tag removed',
            "Tag: {$tagName}",
            ['tag_name' => $tagName]
        );
    }

    public function logWorkflowEntered(Student $student, string $workflowName, int $workflowId): ContactActivity
    {
        return $this->log(
            $student,
            self::TYPE_WORKFLOW_ENTERED,
            'Entered workflow',
            "Workflow: {$workflowName}",
            ['workflow_name' => $workflowName, 'workflow_id' => $workflowId]
        );
    }

    public function logWorkflowCompleted(Student $student, string $workflowName, int $workflowId): ContactActivity
    {
        return $this->log(
            $student,
            self::TYPE_WORKFLOW_COMPLETED,
            'Completed workflow',
            "Workflow: {$workflowName}",
            ['workflow_name' => $workflowName, 'workflow_id' => $workflowId]
        );
    }

    public function logWorkflowExited(Student $student, string $workflowName, int $workflowId, ?string $reason = null): ContactActivity
    {
        return $this->log(
            $student,
            self::TYPE_WORKFLOW_EXITED,
            'Exited workflow',
            "Workflow: {$workflowName}".($reason ? " - Reason: {$reason}" : ''),
            ['workflow_name' => $workflowName, 'workflow_id' => $workflowId, 'reason' => $reason]
        );
    }

    public function logNoteAdded(Student $student, string $note): ContactActivity
    {
        return $this->log(
            $student,
            self::TYPE_NOTE_ADDED,
            'Note added',
            substr($note, 0, 200).(strlen($note) > 200 ? '...' : ''),
            ['note' => $note]
        );
    }

    public function logProfileUpdated(Student $student, array $changedFields): ContactActivity
    {
        $fieldNames = implode(', ', array_keys($changedFields));

        return $this->log(
            $student,
            self::TYPE_PROFILE_UPDATED,
            'Profile updated',
            "Updated fields: {$fieldNames}",
            ['changed_fields' => $changedFields]
        );
    }

    public function logLogin(Student $student, ?string $ipAddress = null): ContactActivity
    {
        return $this->log(
            $student,
            self::TYPE_LOGIN,
            'Logged in',
            $ipAddress ? "IP: {$ipAddress}" : null,
            ['ip_address' => $ipAddress]
        );
    }

    public function getActivities(Student $student, int $limit = 50): Collection
    {
        return $student->contactActivities()
            ->with('performedBy')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getActivitiesByType(Student $student, string $type, int $limit = 50): Collection
    {
        return $student->contactActivities()
            ->where('type', $type)
            ->with('performedBy')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getRecentActivities(Student $student, int $days = 30): Collection
    {
        return $student->contactActivities()
            ->where('created_at', '>=', now()->subDays($days))
            ->with('performedBy')
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
