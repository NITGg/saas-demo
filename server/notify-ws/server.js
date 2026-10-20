'use strict';

// Academy realtime notification relay.
//
// Two surfaces:
//   1. Socket.IO  — browsers connect, authenticate with an HMAC token minted by Moodle, and join a
//                   private per-user room ("user:<id>"). They receive a "notification" event when
//                   one is pushed for them.
//   2. POST /emit — server-to-server only (Moodle's event observer). Authenticated with a shared
//                   internal key. Emits a "notification" event into one user's room.
//
// No database, no Moodle bootstrap: the token is self-verifying (HMAC), so a connection costs almost
// nothing and we only do work when a notification actually fires.

const http = require('http');
const crypto = require('crypto');
const express = require('express');
const { Server } = require('socket.io');

const PORT = parseInt(process.env.PORT || '3100', 10);
// HMAC secret shared with Moodle (signs the per-user client token).
const SECRET = process.env.NOTIFY_SECRET || 'academy-internal-secret';
// Key for the server-to-server /emit endpoint. Defaults to the same secret.
const INTERNAL_KEY = process.env.INTERNAL_KEY || SECRET;
// Socket.IO path. Locally "/socket.io"; behind the prod reverse proxy "/notify-ws/socket.io".
const SOCKET_PATH = process.env.SOCKET_PATH || '/socket.io';
// Comma-separated allowed browser origins, or "*" for any (dev only).
const ALLOWED_ORIGIN = process.env.ALLOWED_ORIGIN || '*';

const app = express();
app.use(express.json({ limit: '64kb' }));

const server = http.createServer(app);
const io = new Server(server, {
  path: SOCKET_PATH,
  cors: {
    origin: ALLOWED_ORIGIN === '*' ? true : ALLOWED_ORIGIN.split(',').map((s) => s.trim()),
    methods: ['GET', 'POST'],
  },
});

/**
 * Verify a Moodle-minted token of the form "<userid>.<exp>.<sig>" where
 * sig = HMAC-SHA256("<userid>.<exp>", SECRET). Returns the userid or null.
 */
function verifyToken(token) {
  if (!token || typeof token !== 'string') {
    return null;
  }
  const parts = token.split('.');
  if (parts.length !== 3) {
    return null;
  }
  const [userid, exp, sig] = parts;
  const expected = crypto.createHmac('sha256', SECRET).update(userid + '.' + exp).digest('hex');
  const a = Buffer.from(sig);
  const b = Buffer.from(expected);
  if (a.length !== b.length || !crypto.timingSafeEqual(a, b)) {
    return null;
  }
  if (!/^\d+$/.test(exp) || parseInt(exp, 10) * 1000 < Date.now()) {
    return null; // expired
  }
  const uid = parseInt(userid, 10);
  return uid > 0 ? uid : null;
}

io.on('connection', (socket) => {
  const auth = socket.handshake.auth || {};
  const uid = verifyToken(auth.token);
  if (!uid) {
    socket.disconnect(true);
    return;
  }
  socket.join('user:' + uid);
});

// Server-to-server: Moodle pushes a notification for a single user.
app.post('/emit', (req, res) => {
  if (req.get('x-internal-key') !== INTERNAL_KEY) {
    return res.status(403).json({ error: 'forbidden' });
  }
  const userid = parseInt(req.body && req.body.userid, 10);
  if (!userid) {
    return res.status(400).json({ error: 'userid required' });
  }
  io.to('user:' + userid).emit('notification', (req.body && req.body.notification) || {});
  return res.json({ status: 'ok' });
});

app.get('/health', (req, res) => res.json({ status: 'ok' }));

server.listen(PORT, () => {
  // eslint-disable-next-line no-console
  console.log('academy-notify-ws listening on ' + PORT + ' (socket path ' + SOCKET_PATH + ')');
});
