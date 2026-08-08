<div>
    <div class="card border-info mt-3">
        <div class="card-header bg-info text-white">
            <h6 class="mb-0">
                <i class="fas fa-clipboard-check me-1"></i>
                AI Form Audit
            </h6>
        </div>
        <div class="card-body">

            @if($generationJob && $generationJob->status === 'failed')
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-times-circle me-1"></i>
                    Audit failed. {{ $generationJob->error }}
                </div>
            @endif

            @if(! $generationJob || $generationJob->status === 'failed')
                <p class="text-muted mb-3">
                    Have the AI review this form for validation quality, missing required fields,
                    weak labels and UX issues &mdash; then apply the suggested fixes in one click.
                </p>
                <button type="button" wire:click="runAudit" class="btn btn-outline-info">
                    <i class="fas fa-clipboard-check me-1"></i> Run AI Audit
                </button>
            @endif

            @if($generationJob && in_array($generationJob->status, ['queued', 'processing'], true))
                <div wire:poll.2s="refreshStatus" class="text-center py-4">
                    <div class="spinner-border text-info" role="status"></div>
                    <p class="mt-3 mb-0"><strong>Auditing your form...</strong></p>
                </div>
            @endif

            @if($report)
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h6 class="mb-1">
                            Score:
                            <span class="badge bg-{{ $report['score'] >= 80 ? 'success' : ($report['score'] >= 50 ? 'warning' : 'danger') }}">
                                {{ $report['score'] }}/100
                            </span>
                        </h6>
                        <p class="text-muted small mb-0">{{ $report['summary'] }}</p>
                    </div>
                    <div>
                        <button type="button" wire:click="applyFixes" class="btn btn-success btn-sm">
                            <i class="fas fa-wrench me-1"></i> Apply Suggested Fixes
                        </button>
                        <button type="button" wire:click="resetAudit" class="btn btn-outline-secondary btn-sm ms-1">
                            <i class="fas fa-rotate me-1"></i> New Audit
                        </button>
                    </div>
                </div>

                @if(empty($report['issues']))
                    <div class="alert alert-success py-2" role="alert">
                        <i class="fas fa-check-circle me-1"></i> No issues found. Great form!
                    </div>
                @else
                    <div class="list-group">
                        @foreach($report['issues'] as $issue)
                            <div class="list-group-item list-group-item-action">
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong>
                                        <i class="fas fa-{{ $issue['severity'] === 'high' ? 'exclamation-circle text-danger' : ($issue['severity'] === 'medium' ? 'exclamation-triangle text-warning' : 'info-circle text-secondary') }} me-1"></i>
                                        {{ $issue['title'] }}
                                    </strong>
                                    <span class="badge bg-{{ $issue['severity'] === 'high' ? 'danger' : ($issue['severity'] === 'medium' ? 'warning' : 'secondary') }}">
                                        {{ $issue['severity'] }}
                                    </span>
                                </div>
                                @if(! empty($issue['detail']))
                                    <p class="small text-muted mb-0 mt-1">{{ $issue['detail'] }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            @endif

        </div>
    </div>
</div>
