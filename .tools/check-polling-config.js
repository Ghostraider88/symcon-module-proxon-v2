const fs = require('fs');

const modules = [
    'Proxon FWT Central Panel',
    'Proxon FWT Room Panel',
    'Proxon FWT System Control',
    'Proxon FWT System Control Advanced',
    'Proxon FWT System Monitoring',
    'Proxon T300 Control',
    'Proxon T300 Monitoring'
];

for (const module of modules) {
    const form = JSON.parse(fs.readFileSync(`${module}/form.json`, 'utf8'));
    const interval = form.elements.find(element => element.name === 'Interval');
    if (!interval || interval.minimum !== 0 || interval.maximum !== 86400) {
        throw new Error(`${module}: interval must allow manual-off 0 and stop at 86400 seconds`);
    }

    const statusCodes = new Set((form.status ?? []).map(status => status.code));
    if (!statusCodes.has(104) || !statusCodes.has(202) || !statusCodes.has(203)) {
        throw new Error(`${module}: disabled, failed, and invalid polling states must be declared`);
    }

    const locale = JSON.parse(fs.readFileSync(`${module}/locale.json`, 'utf8'));
    const german = locale.translations?.de ?? {};
    if (!german['Automatic polling is disabled; use Update Values for a manual poll.'] ||
        !german['Polling interval must be 0 or between 5 and 86400 seconds.'] ||
        !german['Modbus read failed or no active parent connection is available.']) {
        throw new Error(`${module}: polling status captions need German translations`);
    }
}

console.log('Polling forms, status messages, and German translations passed.');
