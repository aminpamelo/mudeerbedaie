<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;

class AudienceRuleBuilder
{
    /**
     * Available rule fields with their configuration.
     *
     * @return array<string, array{label: string, group: string, operators: array<string, string>, type: string}>
     */
    public static function availableFields(): array
    {
        return [
            // Spending & Orders
            'total_spending' => [
                'label' => 'Total Spending (RM)',
                'group' => 'Spending & Orders',
                'operators' => ['>' => 'greater than', '<' => 'less than', '>=' => 'at least', '<=' => 'at most', '=' => 'equals', 'between' => 'between'],
                'type' => 'number',
            ],
            'order_count' => [
                'label' => 'Order Count',
                'group' => 'Spending & Orders',
                'operators' => ['>' => 'greater than', '<' => 'less than', '>=' => 'at least', '<=' => 'at most', '=' => 'equals', 'between' => 'between'],
                'type' => 'number',
            ],
            'last_order_date' => [
                'label' => 'Last Order Date',
                'group' => 'Spending & Orders',
                'operators' => ['before' => 'before', 'after' => 'after', 'in_last_days' => 'in last X days'],
                'type' => 'date',
            ],
            'has_paid_orders' => [
                'label' => 'Has Paid Orders',
                'group' => 'Spending & Orders',
                'operators' => ['is' => 'is'],
                'type' => 'boolean',
            ],

            // Enrollment & Courses
            'enrollment_count' => [
                'label' => 'Enrollment Count',
                'group' => 'Enrollment & Courses',
                'operators' => ['>' => 'greater than', '<' => 'less than', '>=' => 'at least', '<=' => 'at most', '=' => 'equals'],
                'type' => 'number',
            ],
            'enrolled_in_class' => [
                'label' => 'Enrolled In Class',
                'group' => 'Enrollment & Courses',
                'operators' => ['is' => 'is', 'is_not' => 'is not'],
                'type' => 'class',
            ],
            'enrollment_status' => [
                'label' => 'Enrollment Status',
                'group' => 'Enrollment & Courses',
                'operators' => ['is' => 'is', 'is_not' => 'is not'],
                'type' => 'enrollment_status',
            ],
            'subscription_status' => [
                'label' => 'Subscription Status',
                'group' => 'Enrollment & Courses',
                'operators' => ['is' => 'is', 'is_not' => 'is not'],
                'type' => 'subscription_status',
            ],

            // Demographics & Profile
            'student_status' => [
                'label' => 'Student Status',
                'group' => 'Demographics & Profile',
                'operators' => ['is' => 'is', 'is_not' => 'is not'],
                'type' => 'student_status',
            ],
            'country' => [
                'label' => 'Country',
                'group' => 'Demographics & Profile',
                'operators' => ['is' => 'is', 'is_not' => 'is not'],
                'type' => 'country',
            ],
            'state' => [
                'label' => 'State',
                'group' => 'Demographics & Profile',
                'operators' => ['is' => 'is', 'is_not' => 'is not'],
                'type' => 'text',
            ],
            'gender' => [
                'label' => 'Gender',
                'group' => 'Demographics & Profile',
                'operators' => ['is' => 'is', 'is_not' => 'is not'],
                'type' => 'gender',
            ],
            'age' => [
                'label' => 'Age',
                'group' => 'Demographics & Profile',
                'operators' => ['>' => 'greater than', '<' => 'less than', '>=' => 'at least', '<=' => 'at most', 'between' => 'between'],
                'type' => 'number',
            ],
            'registered_date' => [
                'label' => 'Registered Date',
                'group' => 'Demographics & Profile',
                'operators' => ['before' => 'before', 'after' => 'after', 'in_last_days' => 'in last X days'],
                'type' => 'date',
            ],
        ];
    }

    /**
     * Build an Eloquent query from an array of rules.
     *
     * @param  array<int, array{field: string, operator: string, value: mixed, value2?: mixed}>  $rules
     * @param  string  $matchMode  'all' (AND) or 'any' (OR)
     */
    public static function buildQuery(array $rules, string $matchMode = 'all'): Builder
    {
        $query = Student::query()->with(['user']);

        if (empty($rules)) {
            return $query;
        }

        $validRules = array_filter($rules, fn ($r) => ! empty($r['field']) && ! empty($r['operator']) && (isset($r['value']) && $r['value'] !== ''));

        if (empty($validRules)) {
            return $query;
        }

        $method = $matchMode === 'any' ? 'orWhere' : 'where';

        $query->where(function (Builder $q) use ($validRules, $method) {
            foreach ($validRules as $rule) {
                $q->$method(function (Builder $subQ) use ($rule) {
                    self::applyRule($subQ, $rule);
                });
            }
        });

        return $query;
    }

    private static function applyRule(Builder $query, array $rule): void
    {
        $field = $rule['field'];
        $operator = $rule['operator'];
        $value = $rule['value'];
        $value2 = $rule['value2'] ?? null;

        match ($field) {
            'total_spending' => self::applySpendingRule($query, $operator, $value, $value2),
            'order_count' => self::applyOrderCountRule($query, $operator, $value, $value2),
            'last_order_date' => self::applyLastOrderDateRule($query, $operator, $value),
            'has_paid_orders' => self::applyHasPaidOrdersRule($query, $value),
            'enrollment_count' => self::applyEnrollmentCountRule($query, $operator, $value),
            'enrolled_in_class' => self::applyEnrolledInClassRule($query, $operator, $value),
            'enrollment_status' => self::applyEnrollmentStatusRule($query, $operator, $value),
            'subscription_status' => self::applySubscriptionStatusRule($query, $operator, $value),
            'student_status' => self::applyStudentStatusRule($query, $operator, $value),
            'country' => self::applyCountryRule($query, $operator, $value),
            'state' => self::applyStateRule($query, $operator, $value),
            'gender' => self::applyGenderRule($query, $operator, $value),
            'age' => self::applyAgeRule($query, $operator, $value, $value2),
            'registered_date' => self::applyRegisteredDateRule($query, $operator, $value),
            default => null,
        };
    }

    private static function applySpendingRule(Builder $query, string $operator, mixed $value, mixed $value2): void
    {
        $subquery = \App\Models\ProductOrder::selectRaw('COALESCE(SUM(total_amount), 0)')
            ->whereColumn('student_id', 'students.id')
            ->whereNotNull('paid_time');

        if ($operator === 'between') {
            $query->whereRaw("({$subquery->toSql()}) >= ?", array_merge($subquery->getBindings(), [(float) $value]))
                ->whereRaw("({$subquery->toSql()}) <= ?", array_merge($subquery->getBindings(), [(float) $value2]));
        } else {
            $sqlOp = self::mapOperator($operator);
            $query->whereRaw("({$subquery->toSql()}) {$sqlOp} ?", array_merge($subquery->getBindings(), [(float) $value]));
        }
    }

    private static function applyOrderCountRule(Builder $query, string $operator, mixed $value, mixed $value2): void
    {
        $subquery = \App\Models\ProductOrder::selectRaw('COUNT(*)')
            ->whereColumn('student_id', 'students.id')
            ->whereNotNull('paid_time');

        if ($operator === 'between') {
            $query->whereRaw("({$subquery->toSql()}) >= ?", array_merge($subquery->getBindings(), [(int) $value]))
                ->whereRaw("({$subquery->toSql()}) <= ?", array_merge($subquery->getBindings(), [(int) $value2]));
        } else {
            $sqlOp = self::mapOperator($operator);
            $query->whereRaw("({$subquery->toSql()}) {$sqlOp} ?", array_merge($subquery->getBindings(), [(int) $value]));
        }
    }

    private static function applyLastOrderDateRule(Builder $query, string $operator, mixed $value): void
    {
        if ($operator === 'in_last_days') {
            $date = now()->subDays((int) $value)->startOfDay();
            $query->whereHas('paidOrders', function (Builder $q) use ($date) {
                $q->where('paid_time', '>=', $date);
            });
        } elseif ($operator === 'before') {
            $query->whereHas('paidOrders', function (Builder $q) use ($value) {
                $q->where('paid_time', '<', $value);
            });
        } elseif ($operator === 'after') {
            $query->whereHas('paidOrders', function (Builder $q) use ($value) {
                $q->where('paid_time', '>', $value);
            });
        }
    }

    private static function applyHasPaidOrdersRule(Builder $query, mixed $value): void
    {
        if ($value === 'yes') {
            $query->has('paidOrders');
        } else {
            $query->doesntHave('paidOrders');
        }
    }

    private static function applyEnrollmentCountRule(Builder $query, string $operator, mixed $value): void
    {
        $sqlOp = self::mapOperator($operator);
        $query->has('enrollments', $sqlOp, (int) $value);
    }

    private static function applyEnrolledInClassRule(Builder $query, string $operator, mixed $value): void
    {
        if ($operator === 'is') {
            $query->whereHas('classes', fn (Builder $q) => $q->where('classes.id', $value));
        } else {
            $query->whereDoesntHave('classes', fn (Builder $q) => $q->where('classes.id', $value));
        }
    }

    private static function applyEnrollmentStatusRule(Builder $query, string $operator, mixed $value): void
    {
        if ($operator === 'is') {
            $query->whereHas('enrollments', fn (Builder $q) => $q->where('status', $value));
        } else {
            $query->whereDoesntHave('enrollments', fn (Builder $q) => $q->where('status', $value));
        }
    }

    private static function applySubscriptionStatusRule(Builder $query, string $operator, mixed $value): void
    {
        if ($operator === 'is') {
            $query->whereHas('enrollments', fn (Builder $q) => $q->where('subscription_status', $value));
        } else {
            $query->whereDoesntHave('enrollments', fn (Builder $q) => $q->where('subscription_status', $value));
        }
    }

    private static function applyStudentStatusRule(Builder $query, string $operator, mixed $value): void
    {
        if ($operator === 'is') {
            $query->where('status', $value);
        } else {
            $query->where('status', '!=', $value);
        }
    }

    private static function applyCountryRule(Builder $query, string $operator, mixed $value): void
    {
        if ($operator === 'is') {
            $query->where('country', $value);
        } else {
            $query->where('country', '!=', $value);
        }
    }

    private static function applyStateRule(Builder $query, string $operator, mixed $value): void
    {
        if ($operator === 'is') {
            $query->where('state', $value);
        } else {
            $query->where('state', '!=', $value);
        }
    }

    private static function applyGenderRule(Builder $query, string $operator, mixed $value): void
    {
        if ($operator === 'is') {
            $query->where('gender', $value);
        } else {
            $query->where('gender', '!=', $value);
        }
    }

    private static function applyAgeRule(Builder $query, string $operator, mixed $value, mixed $value2): void
    {
        // Age is calculated from date_of_birth
        // age > X means date_of_birth < now - X years
        if ($operator === 'between') {
            $olderDate = now()->subYears((int) $value2)->startOfDay();
            $youngerDate = now()->subYears((int) $value)->startOfDay();
            $query->whereNotNull('date_of_birth')
                ->where('date_of_birth', '<=', $youngerDate)
                ->where('date_of_birth', '>=', $olderDate);
        } else {
            $sqlOp = self::mapOperator($operator);
            // Reverse logic: age > 30 means DOB < (now - 30 years)
            $reversedOp = match ($sqlOp) {
                '>' => '<',
                '<' => '>',
                '>=' => '<=',
                '<=' => '>=',
                default => $sqlOp,
            };
            $date = now()->subYears((int) $value)->startOfDay();
            $query->whereNotNull('date_of_birth')->where('date_of_birth', $reversedOp, $date);
        }
    }

    private static function applyRegisteredDateRule(Builder $query, string $operator, mixed $value): void
    {
        if ($operator === 'in_last_days') {
            $query->where('created_at', '>=', now()->subDays((int) $value)->startOfDay());
        } elseif ($operator === 'before') {
            $query->where('created_at', '<', $value);
        } elseif ($operator === 'after') {
            $query->where('created_at', '>', $value);
        }
    }

    private static function mapOperator(string $operator): string
    {
        return match ($operator) {
            '>' => '>',
            '<' => '<',
            '>=' => '>=',
            '<=' => '<=',
            '=' => '=',
            default => '=',
        };
    }
}
