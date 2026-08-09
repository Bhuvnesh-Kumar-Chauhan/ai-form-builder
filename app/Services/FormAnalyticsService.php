<?php

namespace App\Services;

use App\Models\Form;
use App\Models\FormAnalytic;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Records and aggregates the public fill funnel for a form.
 *
 * Event lifecycle (all keyed by a per-visitor session id):
 *   view      - the fill page was loaded
 *   start     - the visitor made their first interaction (or submitted)
 *   field_interaction - the visitor reached a specific step (event_data.step)
 *   complete  - a submission was stored
 *   abandon   - a visitor left after starting but before completing
 *
 * view/start/complete are de-duplicated per session, so page reloads and
 * Livewire re-renders never double count a visitor.
 */
class FormAnalyticsService
{
    public const EVENT_VIEW = 'view';

    public const EVENT_START = 'start';

    public const EVENT_COMPLETE = 'complete';

    public const EVENT_ABANDON = 'abandon';

    public const EVENT_STEP = 'field_interaction';

    /** Event types that are de-duplicated per form + session. */
    protected const UNIQUE_EVENTS = [
        self::EVENT_VIEW,
        self::EVENT_START,
        self::EVENT_COMPLETE,
    ];

    /**
     * Record an analytics event for a visit.
     *
     * @return bool whether a row was actually written (false when de-duplicated)
     */
    public function recordEvent(
        Form $form,
        string $eventType,
        string $sessionId,
        array $eventData = [],
        ?string $ipAddress = null,
        ?\DateTimeInterface $occurredAt = null
    ): bool {
        $occurredAt = $occurredAt ?? now();

        // Abandon only makes sense for a session that started but never completed.
        if ($eventType === self::EVENT_ABANDON) {
            $started = FormAnalytic::where('form_id', $form->id)
                ->where('session_id', $sessionId)
                ->where('event_type', self::EVENT_START)
                ->exists();
            $completed = FormAnalytic::where('form_id', $form->id)
                ->where('session_id', $sessionId)
                ->where('event_type', self::EVENT_COMPLETE)
                ->exists();

            if (! $started || $completed) {
                return false;
            }
        }

        if (in_array($eventType, self::UNIQUE_EVENTS, true)) {
            $exists = FormAnalytic::where('form_id', $form->id)
                ->where('session_id', $sessionId)
                ->where('event_type', $eventType)
                ->exists();

            if ($exists) {
                return false;
            }
        }

        // Attach the session's start time to completions so the average fill
        // time can be read straight off the row.
        if ($eventType === self::EVENT_COMPLETE) {
            $start = FormAnalytic::where('form_id', $form->id)
                ->where('session_id', $sessionId)
                ->where('event_type', self::EVENT_START)
                ->latest('occurred_at')
                ->first();

            if ($start) {
                $eventData['started_at'] = $start->occurred_at->toIso8601String();
            }
        }

        FormAnalytic::create([
            'form_id' => $form->id,
            'session_id' => $sessionId,
            'event_type' => $eventType,
            'event_data' => $eventData,
            'ip_address' => $ipAddress,
            'occurred_at' => $occurredAt,
        ]);

        return true;
    }

    public function recordView(Form $form, string $sessionId, ?string $ip = null): bool
    {
        return $this->recordEvent($form, self::EVENT_VIEW, $sessionId, [], $ip);
    }

    public function recordStart(Form $form, string $sessionId, ?string $ip = null): bool
    {
        return $this->recordEvent($form, self::EVENT_START, $sessionId, [], $ip);
    }

    public function recordStep(Form $form, string $sessionId, int $step, ?string $ip = null): bool
    {
        return $this->recordEvent($form, self::EVENT_STEP, $sessionId, ['step' => $step], $ip);
    }

    public function recordComplete(Form $form, string $sessionId, array $eventData = [], ?string $ip = null): bool
    {
        return $this->recordEvent($form, self::EVENT_COMPLETE, $sessionId, $eventData, $ip);
    }

    public function recordAbandon(Form $form, string $sessionId, ?string $ip = null): bool
    {
        return $this->recordEvent($form, self::EVENT_ABANDON, $sessionId, [], $ip);
    }

    /**
     * Top-level funnel numbers for the dashboard.
     *
     * @param  int|null  $days  lookback window; null = all time
     * @return array{totals: array, rates: array}
     */
    public function summary(Form $form, ?int $days = 30): array
    {
        $query = fn ($eventType) => FormAnalytic::query()
            ->where('form_id', $form->id)
            ->where('event_type', $eventType)
            ->when($days, fn ($q) => $q->where('occurred_at', '>=', Carbon::now()->subDays($days)));

        $views = $query(self::EVENT_VIEW)->count();
        $started = $query(self::EVENT_START)->count();
        $completed = $query(self::EVENT_COMPLETE)->count();

        // Distinct sessions that loaded the form (approximation of unique visitors).
        $uniqueSessions = FormAnalytic::where('form_id', $form->id)
            ->when($days, fn ($q) => $q->where('occurred_at', '>=', Carbon::now()->subDays($days)))
            ->distinct()
            ->count('session_id');

        return [
            'totals' => [
                'views' => $views,
                'started' => $started,
                'completed' => $completed,
                'abandoned' => max(0, $started - $completed),
                'unique_sessions' => $uniqueSessions,
            ],
            'rates' => [
                'start_rate' => $started > 0 ? round($started / max(1, $views) * 100, 1) : 0.0,
                'completion_rate' => $started > 0 ? round($completed / $started * 100, 1) : 0.0,
                'view_to_complete_rate' => $views > 0 ? round($completed / $views * 100, 1) : 0.0,
                'abandonment_rate' => $started > 0 ? round(max(0, $started - $completed) / $started * 100, 1) : 0.0,
            ],
        ];
    }

    /**
     * Reach / drop-off per step for the funnel breakdown.
     *
     * @return array<int, array{step: int, reached: int, completed: int, dropoff: int}>
     */
    public function stepFunnel(Form $form, ?int $days = 30): array
    {
        $totalSteps = max(1, $form->is_multi_step ? (int) $form->fields->max('step') : 1);

        $interactions = FormAnalytic::where('form_id', $form->id)
            ->where('event_type', self::EVENT_STEP)
            ->when($days, fn ($q) => $q->where('occurred_at', '>=', Carbon::now()->subDays($days)))
            ->get(['session_id', 'event_data']);

        $completedSessions = FormAnalytic::where('form_id', $form->id)
            ->where('event_type', self::EVENT_COMPLETE)
            ->when($days, fn ($q) => $q->where('occurred_at', '>=', Carbon::now()->subDays($days)))
            ->distinct()
            ->pluck('session_id')
            ->all();

        // Per session, the furthest step reached.
        $furthest = [];
        foreach ($interactions as $event) {
            $step = (int) ($event->event_data['step'] ?? 1);
            $session = $event->session_id;
            $furthest[$session] = max($furthest[$session] ?? 0, $step);
        }

        $reached = array_fill(1, $totalSteps, 0);
        foreach ($furthest as $furthestStep) {
            for ($step = 1; $step <= min($furthestStep, $totalSteps); $step++) {
                $reached[$step]++;
            }
        }

        $completedCount = count($completedSessions);

        $rows = [];
        foreach ($reached as $step => $count) {
            $rows[] = [
                'step' => $step,
                'reached' => $count,
                'completed' => $step === $totalSteps ? $completedCount : 0,
                'dropoff' => max(0, $count - $completedCount),
            ];
        }

        return $rows;
    }

    /**
     * Average time between a session's start and its completion, in seconds.
     */
    public function avgCompletionSeconds(Form $form, ?int $days = 30): ?int
    {
        $completions = FormAnalytic::where('form_id', $form->id)
            ->where('event_type', self::EVENT_COMPLETE)
            ->when($days, fn ($q) => $q->where('occurred_at', '>=', Carbon::now()->subDays($days)))
            ->get(['session_id', 'occurred_at', 'event_data']);

        $total = 0;
        $count = 0;

        foreach ($completions as $complete) {
            if (! empty($complete->event_data['started_at'])) {
                $startedAt = Carbon::parse($complete->event_data['started_at']);
                $total += max(0, $complete->occurred_at->getTimestamp() - $startedAt->getTimestamp());
                $count++;
            }
        }

        return $count > 0 ? (int) round($total / $count) : null;
    }

    /**
     * Daily views/starts/completions for the lookback window.
     *
     * @return Collection<int, array{date: string, views: int, started: int, completed: int}>
     */
    public function activitySeries(Form $form, int $days = 14): Collection
    {
        $from = Carbon::now()->subDays($days - 1)->startOfDay();
        $rows = FormAnalytic::where('form_id', $form->id)
            ->where('occurred_at', '>=', $from)
            ->get(['event_type', 'occurred_at']);

        $series = [];
        for ($offset = 0; $offset < $days; $offset++) {
            $date = $from->copy()->addDays($offset);
            $series[$date->format('Y-m-d')] = [
                'date' => $date->format('M j'),
                'views' => 0,
                'started' => 0,
                'completed' => 0,
            ];
        }

        foreach ($rows as $row) {
            $key = $row->occurred_at->format('Y-m-d');
            if (! isset($series[$key])) {
                continue;
            }
            if ($row->event_type === self::EVENT_VIEW) {
                $series[$key]['views']++;
            } elseif ($row->event_type === self::EVENT_START) {
                $series[$key]['started']++;
            } elseif ($row->event_type === self::EVENT_COMPLETE) {
                $series[$key]['completed']++;
            }
        }

        return collect(array_values($series));
    }

    /**
     * Most recent events for the activity feed.
     */
    public function recent(Form $form, int $limit = 15): Collection
    {
        return FormAnalytic::where('form_id', $form->id)
            ->latest('occurred_at')
            ->limit($limit)
            ->get();
    }
}
