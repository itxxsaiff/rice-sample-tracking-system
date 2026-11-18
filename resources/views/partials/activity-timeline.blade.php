@if ($activities->isNotEmpty())
    <ul class="timeline">
        @foreach ($activities as $activity)
            @php [$icon, $tone] = $activity->icon(); @endphp
            <li>
                <span class="dot tone-{{ $tone }}" style="background: currentColor"></span>
                <div class="small">{{ $activity->description }}</div>
                <div class="meta">{{ $activity->user->name }} · <span title="{{ $activity->created_at->format('d M Y H:i') }}">{{ $activity->created_at->diffForHumans() }}</span></div>
            </li>
        @endforeach
    </ul>
@else
    <p class="text-muted small mb-0">No activity recorded yet.</p>
@endif
