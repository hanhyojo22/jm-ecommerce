@php($timeline = $prescription->timeline())
                @if ($prescription->reviewer && $prescription->status !== 'needs_photo')
            <p class="mt-1">{{ $prescription->status === 'needs_photo' ? 'Clearer photo asked for' : ucfirst($prescription->status) }} by {{ $prescription->reviewer->name }}@if ($prescription->reviewer_license_number) (PRC license {{ $prescription->reviewer_license_number }})@endif on {{ $prescription->reviewed_at->format('M j, Y · g:i A') }}.@if ($prescription->reason) Reason: {{ $prescription->reason }}@endif</p>
                @endif
                <ol class="mt-2 space-y-2 text-sm">
                    @foreach ($timeline as $event)
                        <li>
                            <span class="font-medium">{{ ['prescription.submitted' => 'Customer sent the prescription', 'prescription.resubmitted' => 'Customer sent a new copy', 'prescription.checked' => 'Checked by staff', 'prescription.approved' => 'Prescription approved', 'prescription.rejected' => 'Prescription rejected', 'prescription.needs_photo' => 'Clearer photo requested'][$event->action] ?? $event->action }}</span>
                            <span class="text-muted">· {{ $event->created_at->format('M j, Y · g:i A') }}@if ($event->actor) · {{ $event->actor->name }}@endif</span>
                            @if ($event->new_values['reason'] ?? null)
                                <span class="block text-muted">{{ $event->new_values['reason'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
