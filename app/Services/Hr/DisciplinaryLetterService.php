<?php

namespace App\Services\Hr;

use App\Models\DisciplinaryAction;
use App\Models\LetterTemplate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DisciplinaryLetterService
{
    /**
     * Render the active letter template for the action and store the PDF
     * on the public disk, returning the relative storage path.
     */
    public function generatePdf(DisciplinaryAction $action): string
    {
        $action->loadMissing(['employee.department', 'employee.position']);

        $template = LetterTemplate::active()
            ->ofType($action->type)
            ->first();

        if (! $template) {
            throw new RuntimeException("No active letter template found for type [{$action->type}].");
        }

        $html = $template->render($this->placeholders($action));

        $pdf = Pdf::loadHTML($html)->setPaper('a4');

        $path = "hr/disciplinary/disciplinary_{$action->reference_number}.pdf";
        Storage::disk('public')->put($path, $pdf->output());

        $action->update(['letter_pdf_path' => $path]);

        return $path;
    }

    /**
     * @return array<string, string>
     */
    protected function placeholders(DisciplinaryAction $action): array
    {
        return [
            'employee_name' => $action->employee->full_name,
            'employee_id' => $action->employee->employee_id,
            'position' => $action->employee->position?->title ?? 'N/A',
            'department' => $action->employee->department?->name ?? 'N/A',
            'incident_date' => $action->incident_date->format('d/m/Y'),
            'issued_date' => ($action->issued_date ?? now())->format('d/m/Y'),
            'reason' => $action->reason,
            'response_deadline' => $action->response_deadline?->format('d/m/Y') ?? 'N/A',
            'company_name' => config('app.name'),
        ];
    }
}
