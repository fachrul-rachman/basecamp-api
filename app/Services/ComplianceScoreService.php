<?php

namespace App\Services;

use App\Models\Finding;
use App\Models\MonthlyScore;
use App\Models\Role;
use App\Models\SlaInstance;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Two independent monthly percentage scores (PIC execution, Manager SLA
 * responsiveness) — see docs/superpowers/specs/2026-09-11-compliance-scoring-design.md.
 * A score is computed live from current data unless a locked
 * `monthly_scores` row already exists for that subject+month, in which
 * case that row is returned verbatim and never recomputed.
 */
class ComplianceScoreService
{
    private const DEDUCTION_PER_EVENT = 5;

    /**
     * @return array{score: float, deduction_count: int, locked: bool}
     */
    public function picScore(User $pic, CarbonInterface $month): array
    {
        return $this->resolve(
            MonthlyScore::SUBJECT_PIC,
            $pic,
            $month,
            fn (CarbonInterface $start, CarbonInterface $end) => $this->picDeductionCount($pic, $start, $end)
        );
    }

    /**
     * @return array{score: float, deduction_count: int, locked: bool}
     */
    public function managerScore(User $manager, CarbonInterface $month): array
    {
        return $this->resolve(
            MonthlyScore::SUBJECT_MANAGER,
            $manager,
            $month,
            fn (CarbonInterface $start, CarbonInterface $end) => $this->managerDeductionCount($manager, $start, $end)
        );
    }

    /**
     * @return array{pic_count: int, manager_count: int}
     */
    public function lockMonth(CarbonInterface $month): array
    {
        $picCount = 0;
        $managerCount = 0;

        User::query()->whereHas('roles', fn ($q) => $q->where('code', Role::PIC))->each(function (User $pic) use ($month, &$picCount) {
            $locked = $this->lockOne(
                MonthlyScore::SUBJECT_PIC,
                $pic,
                $month,
                fn (CarbonInterface $start, CarbonInterface $end) => $this->picDeductionCount($pic, $start, $end)
            );
            $picCount += $locked ? 1 : 0;
        });

        User::query()->whereHas('roles', fn ($q) => $q->where('code', Role::MANAGER))->each(function (User $manager) use ($month, &$managerCount) {
            $locked = $this->lockOne(
                MonthlyScore::SUBJECT_MANAGER,
                $manager,
                $month,
                fn (CarbonInterface $start, CarbonInterface $end) => $this->managerDeductionCount($manager, $start, $end)
            );
            $managerCount += $locked ? 1 : 0;
        });

        return ['pic_count' => $picCount, 'manager_count' => $managerCount];
    }

    /**
     * @param  callable(CarbonInterface, CarbonInterface): int  $countDeductions
     * @return array{score: float, deduction_count: int, locked: bool}
     */
    private function resolve(string $subjectType, User $subject, CarbonInterface $month, callable $countDeductions): array
    {
        $periodMonth = $month->copy()->startOfMonth()->toDateString();

        $locked = MonthlyScore::query()
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subject->id)
            ->whereDate('period_month', $periodMonth)
            ->first();

        if ($locked) {
            return ['score' => (float) $locked->score, 'deduction_count' => $locked->deduction_count, 'locked' => true];
        }

        $count = $countDeductions($month->copy()->startOfMonth(), $month->copy()->endOfMonth());

        return ['score' => $this->scoreFor($count), 'deduction_count' => $count, 'locked' => false];
    }

    /**
     * @param  callable(CarbonInterface, CarbonInterface): int  $countDeductions
     */
    private function lockOne(string $subjectType, User $subject, CarbonInterface $month, callable $countDeductions): bool
    {
        $periodMonth = $month->copy()->startOfMonth()->toDateString();

        $exists = MonthlyScore::query()
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subject->id)
            ->whereDate('period_month', $periodMonth)
            ->exists();

        if ($exists) {
            return false;
        }

        $count = $countDeductions($month->copy()->startOfMonth(), $month->copy()->endOfMonth());

        MonthlyScore::create([
            'subject_type' => $subjectType,
            'subject_id' => $subject->id,
            'period_month' => $periodMonth,
            'score' => $this->scoreFor($count),
            'deduction_count' => $count,
            'locked_at' => now(),
        ]);

        return true;
    }

    private function picDeductionCount(User $pic, CarbonInterface $start, CarbonInterface $end): int
    {
        return Finding::query()
            ->where('target_user_id', $pic->id)
            ->where('source_type', Finding::SOURCE_AUTOMATIC)
            ->whereIn('finding_type', [Finding::TYPE_LATE, Finding::TYPE_FAILED])
            ->whereBetween('opened_at', [$start, $end])
            ->where(function ($q) {
                $q->whereNull('resolution_type')
                    ->orWhereNotIn('resolution_type', ['explanation_accepted', 'leave_approved']);
            })
            ->count();
    }

    private function managerDeductionCount(User $manager, CarbonInterface $start, CarbonInterface $end): int
    {
        return SlaInstance::query()
            ->where('responsible_user_id', $manager->id)
            ->where('sla_type', SlaInstance::TYPE_MANAGER)
            ->where('status', SlaInstance::STATUS_BREACHED)
            ->whereBetween('breached_at', [$start, $end])
            ->count();
    }

    private function scoreFor(int $count): float
    {
        return max(0.0, 100.0 - ($count * self::DEDUCTION_PER_EVENT));
    }
}
