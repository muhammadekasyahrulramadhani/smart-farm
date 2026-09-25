/**
 * Throwaway test broker (aedes) for live end-to-end validation on dev
 * machines without Mosquitto. DEV ONLY — never used in production.
 * Usage: node test_broker.js [port]
 */
'use strict';

(async () => {
    const port = parseInt(process.argv[2] || '1883', 10);
    const mod = await import('aedes');
    const AedesClass = mod.Aedes || mod.default;
    const aedes = new AedesClass();
    const server = require('net').createServer(aedes.handle);

    server.listen(port, () => {
        console.log(`TEST BROKER listening on 127.0.0.1:${port}`);
    });

    aedes.on('publish', (packet, client) => {
        if (client) {
            console.log(`[broker] ${packet.topic} <- ${client.id}`);
        }
    });
})();
