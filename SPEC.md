# SehControl V2 - API Spec (MVP)

Base path: /api/v2

## GET /client/policy?device_uid=...
Respuesta estandar:

- 200 OK (permitido)
{
  "allow_connect": true,
  "action": "continue",
  "reason": "ok",
  "ui": {"title":"SehControl","message":"Servicio activo.","level":"info"},
  "sehcontrol_config": {...}
}

- 404 Not Found (no registrado)
{
  "allow_connect": false,
  "action": "retry_later",
  "reason": "unregistered",
  "ui": {"title":"No registrado","message":"Vincule este equipo con un código.","level":"warning"}
}

- 403 Forbidden (denegado)
{
  "allow_connect": false,
  "action": "terminate",
  "reason": "blocked|license_required|expired|limit",
  "ui": {"title":"...","message":"...","level":"error|warning"}
}

## POST /client/pair
Body:
{
  "pair_code": "PAIR-123456",
  "device_uid": "64hex...",
  "rustdesk_id": "105090706",
  "alias": "PC Oficina",
  "platform": "windows",
  "version": "1.3.9"
}

Respuesta 200 OK:
{
  "status": "ok",
  "message": "Equipo emparejado",
  "device": {...}
}

## POST /client/heartbeat
Body:
{ "device_uid": "..." }

200:
{ "status": "ok" }
