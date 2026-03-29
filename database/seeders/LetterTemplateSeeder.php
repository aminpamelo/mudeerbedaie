<?php

namespace Database\Seeders;

use App\Models\LetterTemplate;
use Illuminate\Database\Seeder;

class LetterTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Verbal Warning Letter',
                'type' => 'verbal_warning',
                'content' => '<div style="font-family: Arial, sans-serif; max-width: 700px; margin: 0 auto;">
<p style="text-align: right;">Date: {{issued_date}}</p>
<p><strong>VERBAL WARNING</strong></p>
<p>To: {{employee_name}}<br>Employee ID: {{employee_id}}<br>Position: {{position}}<br>Department: {{department}}</p>
<p>Dear {{employee_name}},</p>
<p>This letter serves as a formal verbal warning regarding the following matter:</p>
<p><strong>Incident Date:</strong> {{incident_date}}</p>
<p><strong>Details:</strong> {{reason}}</p>
<p>This is a verbal warning and is the first step in our disciplinary process. We expect immediate improvement in your conduct/performance. Failure to improve may result in further disciplinary action.</p>
<p>Yours sincerely,<br>{{company_name}} HR Department</p>
</div>',
            ],
            [
                'name' => 'First Written Warning Letter',
                'type' => 'first_written',
                'content' => '<div style="font-family: Arial, sans-serif; max-width: 700px; margin: 0 auto;">
<p style="text-align: right;">Date: {{issued_date}}</p>
<p><strong>FIRST WRITTEN WARNING</strong></p>
<p>To: {{employee_name}}<br>Employee ID: {{employee_id}}<br>Position: {{position}}<br>Department: {{department}}</p>
<p>Dear {{employee_name}},</p>
<p>Further to the verbal warning previously issued, this letter serves as a formal first written warning regarding:</p>
<p><strong>Incident Date:</strong> {{incident_date}}</p>
<p><strong>Details:</strong> {{reason}}</p>
<p>This warning will be placed in your personnel file. Continued failure to improve may lead to further disciplinary action up to and including termination of employment.</p>
<p>Yours sincerely,<br>{{company_name}} HR Department</p>
</div>',
            ],
            [
                'name' => 'Show Cause Letter',
                'type' => 'show_cause',
                'content' => '<div style="font-family: Arial, sans-serif; max-width: 700px; margin: 0 auto;">
<p style="text-align: right;">Date: {{issued_date}}</p>
<p><strong>SHOW CAUSE LETTER</strong></p>
<p>To: {{employee_name}}<br>Employee ID: {{employee_id}}<br>Position: {{position}}<br>Department: {{department}}</p>
<p>Dear {{employee_name}},</p>
<p>You are hereby required to show cause as to why disciplinary action should not be taken against you for the following:</p>
<p><strong>Incident Date:</strong> {{incident_date}}</p>
<p><strong>Details:</strong> {{reason}}</p>
<p>You are required to submit your written explanation by <strong>{{response_deadline}}</strong>. Failure to respond by the deadline will be taken as an admission of the allegations and the company will proceed with appropriate disciplinary action.</p>
<p>Yours sincerely,<br>{{company_name}} HR Department</p>
</div>',
            ],
            [
                'name' => 'Termination Letter',
                'type' => 'termination',
                'content' => '<div style="font-family: Arial, sans-serif; max-width: 700px; margin: 0 auto;">
<p style="text-align: right;">Date: {{issued_date}}</p>
<p><strong>TERMINATION OF EMPLOYMENT</strong></p>
<p>To: {{employee_name}}<br>Employee ID: {{employee_id}}<br>Position: {{position}}<br>Department: {{department}}</p>
<p>Dear {{employee_name}},</p>
<p>Following the domestic inquiry conducted and after careful consideration, the company has decided to terminate your employment effective immediately.</p>
<p><strong>Reason:</strong> {{reason}}</p>
<p>You are required to return all company property and complete the exit process. Your final settlement will be calculated and paid according to the Employment Act 1955.</p>
<p>Yours sincerely,<br>{{company_name}} HR Department</p>
</div>',
            ],
            [
                'name' => 'Resignation Acceptance Letter',
                'type' => 'resignation_acceptance',
                'content' => '<div style="font-family: Arial, sans-serif; max-width: 700px; margin: 0 auto;">
<p style="text-align: right;">Date: {{issued_date}}</p>
<p><strong>ACCEPTANCE OF RESIGNATION</strong></p>
<p>To: {{employee_name}}<br>Employee ID: {{employee_id}}<br>Position: {{position}}<br>Department: {{department}}</p>
<p>Dear {{employee_name}},</p>
<p>We acknowledge receipt of your resignation letter dated {{incident_date}}. Your resignation has been accepted.</p>
<p>Your last working day will be <strong>{{response_deadline}}</strong>.</p>
<p>Please ensure all company property is returned and handover documentation is completed before your last day. Your final settlement will be processed accordingly.</p>
<p>We wish you all the best in your future endeavors.</p>
<p>Yours sincerely,<br>{{company_name}} HR Department</p>
</div>',
            ],
        ];

        foreach ($templates as $template) {
            LetterTemplate::updateOrCreate(
                ['name' => $template['name']],
                array_merge($template, ['is_active' => true])
            );
        }
    }
}
