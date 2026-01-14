<?php

return [
    // API
    'api_base' => env('SEH_API_BASE', 'https://sehcontrol.sehuacho.com/api'),

    // Dominio
    'domain' => env('SEH_DOMAIN', 'sehcontrol.sehuacho.com'),

    // RustDesk / SehControl
    'rendezvous_port' => (int) env('SEH_RENDEZVOUS_PORT', 21116),
    'relay'           => env('SEH_RELAY', 'sehcontrol.sehuacho.com'),
    'key'             => env('SEH_KEY', ''),

    // Admin key
    'admin_key'      => env('SEH_ADMIN_KEY', ''),

    // Admin secret
    'admin_secret' => env('SEH_ADMIN_SECRET', ''),

    // 🔓 Unlock PIN (por ahora fijo)
    'unlock_pin'      => env('SEH_UNLOCK_PIN', ''),

    // Agent defaults
    'heartbeat_seconds' => (int) env('SEH_HEARTBEAT_SECONDS', 300),
    'poll_seconds'      => (float) env('SEH_POLL_SECONDS', 1.0),
    'agent_secret'    => env('SEH_AGENT_SECRET', ''),

];

