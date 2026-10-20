@props(['name' => 'return', 'class' => 'size-5'])

<svg {{ $attributes->class([$class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('return')
            <path d="M3 12a9 9 0 1 0 3-6.7" />
            <path d="M3 4v5h5" />
            @break
        @case('refund')
            <path d="M3 12a9 9 0 1 0 3-6.7" />
            <path d="M3 4v5h5" />
            <path d="M12 8v8M9.5 10.2c0-1 .9-1.7 2.5-1.7s2.5.7 2.5 1.8c0 1.2-1.2 1.6-2.5 1.9s-2.5.8-2.5 1.9c0 1.1.9 1.8 2.5 1.8s2.5-.7 2.5-1.7" />
            @break
        @case('replacement')
            <path d="m12 3 8 4.5v9L12 21l-8-4.5v-9z" />
            <path d="m4 7.5 8 4.5 8-4.5M12 12v9" />
            <path d="M16 4.5 8 9" />
            @break
        @case('package')
            <path d="m12 3 9 5v8l-9 5-9-5V8z" />
            <path d="m3 8 9 5 9-5M12 13v8" />
            @break
        @case('truck')
            <path d="M3 6h11v9H3z" />
            <path d="M14 9h4l3 3v3h-7z" />
            <circle cx="7" cy="18" r="1.5" />
            <circle cx="17" cy="18" r="1.5" />
            @break
        @case('clock')
            <circle cx="12" cy="12" r="9" />
            <path d="M12 7v5l3 2" />
            @break
        @case('calendar')
            <rect x="3" y="5" width="18" height="16" rx="2" />
            <path d="M8 3v4M16 3v4M3 10h18" />
            @break
        @case('shield')
            <path d="M12 3 5 6v5c0 4.6 2.8 8 7 10 4.2-2 7-5.4 7-10V6z" />
            <path d="m9 12 2 2 4-4" />
            @break
        @case('scale')
            <path d="M12 4v16M7 20h10" />
            <path d="M6 8 12 4l6 4" />
            <path d="M6 8l-3 6a3.5 3.5 0 0 0 6 0zM18 8l-3 6a3.5 3.5 0 0 0 6 0z" />
            @break
        @case('chat')
            <path d="M20 15a4 4 0 0 1-4 4H8l-4 3V7a4 4 0 0 1 4-4h8a4 4 0 0 1 4 4z" />
            @break
        @case('mail')
            <rect x="3" y="5" width="18" height="14" rx="2" />
            <path d="m3 7 9 6 9-6" />
            @break
        @case('check')
            <circle cx="12" cy="12" r="9" />
            <path d="m8.5 12 2.5 2.5L16 9.5" />
            @break
        @case('check-plain')
            <path d="m5 12 4 4L19 6" />
            @break
        @case('alert')
            <path d="M12 9v4M12 17h.01" />
            <path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z" />
            @break
        @case('search')
            <circle cx="11" cy="11" r="6" />
            <path d="m16 16 4 4" />
            @break
        @case('chevron-right')
            <path d="m9 6 6 6-6 6" />
            @break
        @case('chevron-down')
            <path d="m6 9 6 6 6-6" />
            @break
        @case('store')
            <path d="M4 10v10h16V10M3 10l2-6h14l2 6M3 10a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0" />
            @break
        @case('camera')
            <path d="M4 7h3l2-3h6l2 3h3v13H4z" />
            <circle cx="12" cy="13" r="3.5" />
            @break
        @case('file')
            <path d="M8 3h7l4 4v14H5V3z" />
            <path d="M15 3v5h5" />
            @break
        @case('spark')
            <path d="m12 3 1.5 5L19 9.5 13.5 11 12 16l-1.5-5L5 9.5 10.5 8z" />
            <path d="M19 15l.7 2.3L22 18l-2.3.7L19 21l-.7-2.3L16 18l2.3-.7z" />
            @break
        @case('lock')
            <rect x="5" y="11" width="14" height="9" rx="2" />
            <path d="M8 11V8a4 4 0 0 1 8 0v3" />
            @break
        @case('rotate')
            <path d="M3 12a9 9 0 1 0 3-6.7" />
            <path d="M3 4v5h5" />
            @break
        @case('x-circle')
            <circle cx="12" cy="12" r="9" />
            <path d="m15 9-6 6M9 9l6 6" />
            @break
        @case('wrench')
            <path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L4 17v3h3l5.3-5.3a4 4 0 0 0 5.4-5.4l-2.3 2.3-2.4-2.4z" />
            @break
        @case('swap')
            <path d="M4 8h13l-3-3M20 16H7l3 3" />
            @break
        @case('plus')
            <path d="M12 5v14M5 12h14" />
            @break
        @case('minus')
            <path d="M5 12h14" />
            @break
        @default
            <circle cx="12" cy="12" r="9" />
            <path d="M12 8v4M12 16h.01" />
    @endswitch
</svg>
