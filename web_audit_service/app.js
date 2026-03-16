const http = require('http');
const { runAudit } = require('./auditRunner');

const PORT = Number(process.env.WEB_AUDIT_PORT || 3100);
const HOST = process.env.WEB_AUDIT_HOST || '127.0.0.1';

function sendJson(res, statusCode, payload) {
  const body = JSON.stringify(payload);
  res.writeHead(statusCode, {
    'Content-Type': 'application/json',
    'Content-Length': Buffer.byteLength(body),
  });
  res.end(body);
}

function readJsonBody(req) {
  return new Promise((resolve, reject) => {
    let raw = '';
    req.on('data', (chunk) => {
      raw += chunk;
      if (raw.length > 1024 * 1024) {
        reject(new Error('Payload demasiado grande'));
      }
    });
    req.on('end', () => {
      try {
        resolve(raw ? JSON.parse(raw) : {});
      } catch (error) {
        reject(error);
      }
    });
    req.on('error', reject);
  });
}

const server = http.createServer(async (req, res) => {
  if (req.method === 'GET' && req.url === '/health') {
    sendJson(res, 200, { ok: true, service: 'web-audit' });
    return;
  }

  if (req.method === 'POST' && req.url === '/audit') {
    try {
      const input = await readJsonBody(req);
      const result = await runAudit(input);
      sendJson(res, result.success ? 200 : 400, result);
    } catch (error) {
      sendJson(res, 500, {
        success: false,
        error: error.message || 'Error inesperado en el servicio de auditoria web.',
      });
    }
    return;
  }

  sendJson(res, 404, { success: false, error: 'Ruta no encontrada' });
});

server.listen(PORT, HOST, () => {
  process.stdout.write(`Web audit service listening on http://${HOST}:${PORT}\n`);
});
