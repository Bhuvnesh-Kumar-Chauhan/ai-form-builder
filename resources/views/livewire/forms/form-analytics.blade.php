<div>
    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0">
                    <i class="fas fa-chart-line"></i> Analytics: {{ $form->title }}
                </h5>
                <div class="d-flex gap-2">
                    <select wire:model="days" class="form-select form-select-sm w-auto">
                        <option value="7">Last 7 days</option>
                        <option value="14">Last 14 days</option>
                        <option value="30">Last 30 days</option>
                        <option value="90">Last 90 days</option>
                        <option value="">All time</option>
                    </select>
                    <a href="{{ route('forms.edit', $form->slug) }}" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-arrow-left"></i> Back to Form
                    </a>
                </div>
            </div>
        </div>
        <div class="card-body">
            @if(($summary['totals']['views'] ?? 0) === 0 && ($summary['totals']['started'] ?? 0) === 0)
                <div class="text-center py-5">
                    <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                    <h6 class="text-muted">No fill activity yet for this period.</h6>
                    <p class="text-muted small mb-0">
                        Share the public link (<code>{{ $form->fill_url }}</code>) to start collecting funnel data.
                    </p>
                </div>
            @else
                <!-- KPI Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-lg-3">
                        <div class="border rounded p-3 text-center bg-light">
                            <div class="fs-3 fw-bold text-primary">{{ $summary['totals']['views'] }}</div>
                            <div class="small text-muted">Views</div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="border rounded p-3 text-center bg-light">
                            <div class="fs-3 fw-bold text-info">{{ $summary['totals']['started'] }}</div>
                            <div class="small text-muted">Started</div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="border rounded p-3 text-center bg-light">
                            <div class="fs-3 fw-bold text-success">{{ $summary['totals']['completed'] }}</div>
                            <div class="small text-muted">Completed</div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="border rounded p-3 text-center bg-light">
                            <div class="fs-3 fw-bold text-danger">{{ $summary['totals']['abandoned'] }}</div>
                            <div class="small text-muted">Abandoned</div>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <!-- Funnel -->
                    <div class="col-lg-6">
                        <div class="border rounded p-3 h-100">
                            <h6 class="mb-3"><i class="fas fa-filter"></i> Conversion Funnel</h6>

                            @php
                                $maxStage = max(1, $summary['totals']['views'], $summary['totals']['started'], $summary['totals']['completed']);
                            @endphp

                            @foreach([
                                ['label' => 'Viewed form', 'value' => $summary['totals']['views'], 'rate' => 100, 'class' => 'bg-secondary'],
                                ['label' => 'Started filling', 'value' => $summary['totals']['started'], 'rate' => $summary['rates']['start_rate'], 'class' => 'bg-info'],
                                ['label' => 'Submitted', 'value' => $summary['totals']['completed'], 'rate' => $summary['rates']['view_to_complete_rate'], 'class' => 'bg-success'],
                            ] as $stage)
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between small mb-1">
                                        <span>{{ $stage['label'] }}</span>
                                        <span>
                                            <strong>{{ $stage['value'] }}</strong>
                                            @if($stage['value'] > 0)
                                                <span class="text-muted">({{ $stage['rate'] }}%)</span>
                                            @endif
                                        </span>
                                    </div>
                                    <div class="progress" style="height: 22px;">
                                        <div class="progress-bar {{ $stage['class'] }}"
                                             style="width: {{ $stage['rate'] }}%;"
                                             role="progressbar"></div>
                                    </div>
                                </div>
                            @endforeach

                            <div class="mt-3 pt-2 border-top">
                                <div class="d-flex justify-content-between small">
                                    <span class="text-muted">Average time to complete</span>
                                    <span>
                                        @if($avgSeconds)
                                            <strong>{{ floor($avgSeconds / 60) }}m {{ $avgSeconds % 60 }}s</strong>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step drop-off -->
                    <div class="col-lg-6">
                        <div class="border rounded p-3 h-100">
                            <h6 class="mb-3"><i class="fas fa-shoe-prints"></i> Step Drop-off</h6>
                            @if(count($stepFunnel) > 1)
                                <table class="table table-sm align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Step</th>
                                            <th>Reached</th>
                                            <th>Completed</th>
                                            <th>Drop-off</th>
                                            <th class="w-25">Rate</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($stepFunnel as $row)
                                            @php
                                                $stepRate = $row['reached'] > 0
                                                    ? ($row['completed'] > 0 ? round($row['completed'] / max(1, $row['reached']) * 100, 1) : 0)
                                                    : 0;
                                            @endphp
                                            <tr>
                                                <td>Step {{ $row['step'] }}</td>
                                                <td>{{ $row['reached'] }}</td>
                                                <td>{{ $row['completed'] }}</td>
                                                <td class="text-danger">{{ $row['dropoff'] }}</td>
                                                <td>
                                                    <div class="progress" style="height: 8px;">
                                                        <div class="progress-bar bg-success" style="width: {{ $stepRate }}%;"></div>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @else
                                <p class="text-muted small mb-0">
                                    This form is a single step. Use multi-step mode to see per-step drop-off.
                                </p>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Activity chart + recent events -->
                <div class="row g-4 mt-0">
                    <div class="col-lg-7">
                        <div class="border rounded p-3">
                            <h6 class="mb-3"><i class="fas fa-calendar-day"></i> Last 14 Days</h6>
                            @php
                                $maxDaily = max(1, $series->flatMap(fn ($day) => [$day['views'], $day['started'], $day['completed']])->max());
                            @endphp
                            <div class="d-flex align-items-end gap-2" style="height: 120px;">
                                @foreach($series as $day)
                                    <div class="flex-grow-1 d-flex flex-column justify-content-end align-items-center" title="{{ $day['date'] }}: {{ $day['views'] }} views, {{ $day['started'] }} started, {{ $day['completed'] }} completed">
                                        <div class="d-flex gap-1 mb-1">
                                            <div class="rounded bg-secondary" style="width: 8px; height: {{ max(2, round($day['views'] / $maxDaily * 100)) }}px;"></div>
                                            <div class="rounded bg-info" style="width: 8px; height: {{ max(2, round($day['started'] / $maxDaily * 100)) }}px;"></div>
                                            <div class="rounded bg-success" style="width: 8px; height: {{ max(2, round($day['completed'] / $maxDaily * 100)) }}px;"></div>
                                        </div>
                                        <small class="text-muted" style="font-size: 9px;">{{ \Illuminate\Support\Str::after($day['date'], ' ') }}</small>
                                    </div>
                                @endforeach
                            </div>
                            <div class="d-flex gap-3 mt-2 small text-muted">
                                <span><span class="d-inline-block rounded bg-secondary" style="width: 10px; height: 10px;"></span> Views</span>
                                <span><span class="d-inline-block rounded bg-info" style="width: 10px; height: 10px;"></span> Started</span>
                                <span><span class="d-inline-block rounded bg-success" style="width: 10px; height: 10px;"></span> Completed</span>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="border rounded p-3 h-100">
                            <h6 class="mb-3"><i class="fas fa-clock"></i> Recent Activity</h6>
                            @forelse($recentEvents as $event)
                                <div class="d-flex align-items-center gap-2 small mb-2">
                                    @if($event->event_type === 'view')
                                        <i class="fas fa-eye text-secondary"></i>
                                    @elseif($event->event_type === 'start')
                                        <i class="fas fa-play text-info"></i>
                                    @elseif($event->event_type === 'complete')
                                        <i class="fas fa-check-circle text-success"></i>
                                    @elseif($event->event_type === 'abandon')
                                        <i class="fas fa-user-slash text-danger"></i>
                                    @else
                                        <i class="fas fa-shoe-prints text-warning"></i>
                                    @endif
                                    <span class="text-capitalize">{{ str_replace('_', ' ', $event->event_type) }}</span>
                                    @if(isset($event->event_data['step']))
                                        <span class="badge bg-light text-dark">step {{ $event->event_data['step'] }}</span>
                                    @endif
                                    <span class="ms-auto text-muted">{{ $event->occurred_at->diffForHumans() }}</span>
                                </div>
                            @empty
                                <p class="text-muted small mb-0">No events recorded.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
