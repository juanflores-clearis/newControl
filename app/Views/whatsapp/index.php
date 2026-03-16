<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>WhatsApp Monitor</title>
    <style>
        body { font-family: Arial; }
        .event { padding: 8px; border-bottom: 1px solid #ccc; }
        .time { color: #888; font-size: 12px; }
    </style>
</head>
<body>

<h1>📱 WhatsApp Monitor</h1>
<div id="events"></div>

<!-- Audio de notificación -->
<audio id="notifySound" src="https://apps.clearis.es/newcontrol/assets/sound/notify.mp3" preload="auto"></audio>

<script>
let lastCount = 0;
let firstLoad = true;

async function loadEvents() {
    const res = await fetch('https://apps.clearis.es/newcontrol/public/?route=/api/whatsapp_events');
    const data = await res.json();

    const container = document.getElementById('events');

    // 🔔 Reproducir sonido SOLO si hay nuevos eventos (y no es la primera carga)
    if (!firstLoad && data.length > lastCount) {
        document.getElementById('notifySound').play().catch(() => {});
    }

    firstLoad = false;
    lastCount = data.length;

    container.innerHTML = '';

    data.reverse().forEach(e => {
        const div = document.createElement('div');
        div.className = 'event';
        div.innerHTML = `
            <div class="time">${e.time}</div>
            <strong>${e.chat}</strong><br>
            ${e.message}
        `;
        container.appendChild(div);
    });
}

loadEvents();
setInterval(loadEvents, 3000); // cada 3 segundos
</script>

</body>
</html>
