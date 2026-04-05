'use strict';

const fs = require('fs');
const path = require('path');

const adminPath = path.join(__dirname, '..', 'assets', 'js', 'admin.js');
const outPath = path.join(__dirname, '..', 'assets', 'js', 'admin', 'calendar-admin-app.js');

const lines = fs.readFileSync(adminPath, 'utf8').split(/\r?\n/);
const body = lines.slice(1590, 2441).join('\n') + '\n';

const header = `/**
 * Calendario admin pagina Operazioni (#fp-exp-calendar-app).
 * Estratto da admin.js: il bundle min non carica admin.js.
 * Rigenerazione: \`node tools/extract-calendar-admin-app.js\` dalla root del plugin.
 */
(function () {
    'use strict';

    window.fpExpAdmin = window.fpExpAdmin || {};

`;

const footer = `
    window.fpExpAdmin.initCalendarApp = initCalendarApp;
})();
`;

fs.writeFileSync(outPath, header + body + footer, 'utf8');
console.log('Wrote', outPath, fs.statSync(outPath).size, 'bytes');
