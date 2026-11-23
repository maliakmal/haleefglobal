<?php
declare(strict_types=1);

function learning_icon(string $name): string
{
    $solid = [
        'linkedin'=>'<path d="M20.4 2H3.6C2.7 2 2 2.7 2 3.6v16.8c0 .9.7 1.6 1.6 1.6h16.8c.9 0 1.6-.7 1.6-1.6V3.6c0-.9-.7-1.6-1.6-1.6ZM8 19H5V9.5h3V19ZM6.5 8.2a1.7 1.7 0 1 1 0-3.4 1.7 1.7 0 0 1 0 3.4ZM19 19h-3v-4.6c0-1.1-.02-2.5-1.55-2.5s-1.8 1.2-1.8 2.4V19h-3V9.5h2.9v1.3h.04a3.2 3.2 0 0 1 2.85-1.55c3 0 3.56 1.96 3.56 4.5V19Z"/>',
        'facebook'=>'<path d="M22 12a10 10 0 1 0-11.56 9.88v-6.99H7.9V12h2.54V9.8c0-2.5 1.49-3.89 3.77-3.89 1.09 0 2.23.2 2.23.2v2.45h-1.26c-1.24 0-1.62.77-1.62 1.56V12h2.76l-.44 2.89h-2.32v6.99A10 10 0 0 0 22 12Z"/>',
    ];
    $outline = [
        'whatsapp'=>'<path d="M20.7 11.8a8.7 8.7 0 0 1-12.9 7.6L3 20.7l1.3-4.5A8.7 8.7 0 1 1 20.7 11.8Z"/><path d="M9 7.4c.3-.1.7 0 .8.4l.7 1.7c.1.3 0 .5-.2.7l-.6.6a6.3 6.3 0 0 0 3.4 3.4l.7-.8c.2-.2.4-.3.7-.1l1.8.9c.2.1.3.3.2.6-.1.7-.8 1.4-1.6 1.5-3.2.4-7.8-3.8-7.8-6.8 0-.9 1-2 1.9-2.1Z"/>',
        'email'=>'<rect x="3" y="5" width="18" height="14" rx="3"/><path d="m4 7 8 6 8-6"/>',
        'copy'=>'<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/>',
        'share'=>'<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 10.5 6.8-4m-6.8 7 6.8 4"/>',
        'external'=>'<path d="M13 4h7v7m0-7-9 9m-1-8H5a1 1 0 0 0-1 1v13a1 1 0 0 0 1 1h13a1 1 0 0 0 1-1v-5"/>',
        'calendar'=>'<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 10h18"/>',
        'clock'=>'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'video'=>'<rect x="3" y="6" width="13" height="12" rx="2"/><path d="m16 10 5-3v10l-5-3"/>',
        'pin'=>'<path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
    ];
    $base = '<svg aria-hidden="true" focusable="false" viewBox="0 0 24 24" ';
    if (isset($solid[$name])) return $base . 'fill="currentColor">' . $solid[$name] . '</svg>';
    return $base . 'fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">' . ($outline[$name] ?? '') . '</svg>';
}
