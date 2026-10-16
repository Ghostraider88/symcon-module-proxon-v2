'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const moduleDirectory = path.join(__dirname, '..', 'Proxon FWT System Control');
const html = fs.readFileSync(path.join(moduleDirectory, 'module.html'), 'utf8')
    .replace('<!-- FWT_SVG -->', fs.readFileSync(path.join(moduleDirectory, 'visualization.svg'), 'utf8'))
    .replace('__INITIAL_DATA__', '{}')
    .replace('__ROOM_LABELS__', JSON.stringify({
        Rooms: 'Rooms',
        'No rooms assigned': 'No rooms assigned',
        'Heating (inferred)': 'Heating*',
        'Cooling (inferred)': 'Cooling*',
        'Mode Inferred': 'Inferred',
        Heating: 'Heating',
        Cooling: 'Cooling',
        Off: 'Off',
        Running: 'Running',
        Current: 'current',
        Stale: 'stale',
        Failed: 'failed',
        Unknown: 'unknown',
        Plant: 'Plant',
        Monitoring: 'Monitoring',
        Modbus: 'Modbus',
        Disconnected: 'disconnected'
    }));
const script = html.match(/<script>([\s\S]*?)<\/script>/)?.[1];
assert.ok(script, 'Tile script exists');

function createElement() {
    const attributes = new Map();
    return {
        dataset: {},
        style: {},
        classList: {
            add() {},
            remove() {},
            toggle() {}
        },
        textContent: '',
        append() {},
        replaceChildren() {},
        setAttribute(name, value) { attributes.set(name, value); },
        removeAttribute(name) { attributes.delete(name); },
        hasAttribute(name) { return attributes.has(name); },
        querySelector() { return createElement(); },
        querySelectorAll() { return []; },
        closest() { return null; }
    };
}

const elements = new Map();
const plant = createElement();
plant.dataset.heatPumpMode = 'unknown';
const document = {
    getElementById(id) {
        if (!elements.has(id)) elements.set(id, createElement());
        return elements.get(id);
    },
    querySelector(selector) { return selector === '.fwt-plant' ? plant : createElement(); },
    querySelectorAll() { return []; },
    createElement() { return createElement(); }
};
let refreshTileQuality = null;
const window = {
    setInterval(callback, delay) {
        assert.equal(delay, 15000, 'Tile quality should refresh every 15 seconds');
        refreshTileQuality = callback;
    }
};
vm.runInNewContext(script, { document, window, Date, Intl, JSON, Number });
assert.equal(typeof refreshTileQuality, 'function', 'Tile quality refresh callback is registered');

const data = {
    systemControl: {
        HeatPumpModeCode: 0,
        SupplyAirTemperature: 30,
        FreshAirTemperature: 20,
        SupplyFanRPM: 800,
        ExtractFanRPM: 800
    },
    monitoring: { CompressorSpeed: 1200 },
    rooms: []
};
window.handleMessage(JSON.stringify({
    systemControl: { ...data.systemControl, HeatPumpModeCode: null },
    monitoring: data.monitoring,
    rooms: []
}));
assert.equal(document.getElementById('heat-pump-mode').textContent, 'Heating*', 'Inference remains available for an unknown mode');

window.handleMessage(JSON.stringify(data));
assert.equal(document.getElementById('heat-pump-mode').textContent, 'Off', 'Reported mode 0 remains Off');
assert.equal(plant.dataset.heatPumpMode, '0', 'Reported mode 0 remains the tile state');

window.handleMessage(data);
assert.equal(document.getElementById('heat-pump-mode').textContent, 'Off', 'Initial object payload remains supported');

const now = Date.now();
const freshEntry = (identifiers = []) => ({
    identifiers,
    lastSuccessTimestamp: now,
    lastAttemptTimestamp: now,
    lastAttemptSucceeded: true
});
const qualityAwareData = {
    connected: true,
    monitoringSelected: true,
    systemControlInterval: 30,
    systemControl: {
        HeatPumpModeCode: null,
        SupplyAirTemperature: 30,
        FreshAirTemperature: 20,
        SupplyFanRPM: 500,
        ExtractFanRPM: 500
    },
    systemControlQuality: {
        'FC4 register 195': freshEntry(),
        'FC4 register 198': freshEntry(),
        'FC4 register 0': freshEntry(),
        'FC4 register 1': freshEntry()
    },
    monitoringInterval: 60,
    monitoring: { CompressorSpeed: 1200 },
    monitoringQuality: { 'FC4 block 184x4': freshEntry(['CompressorSpeed']) },
    rooms: []
};
window.handleMessage(qualityAwareData);
assert.equal(document.getElementById('heat-pump-mode').textContent, 'Heating*', 'Fresh quality data supports mode inference');
assert.equal(
    document.getElementById('compressor-overview-state').textContent,
    new Intl.NumberFormat(undefined, { maximumFractionDigits: 0 }).format(1200) + ' rpm',
    'Fresh compressor speed is shown'
);

qualityAwareData.monitoringQuality['FC4 block 184x4'].lastSuccessTimestamp = Date.now() - 301000;
refreshTileQuality();
assert.equal(document.getElementById('heat-pump-mode').textContent, '—', 'Stale compressor speed cannot infer a heat pump mode');
assert.equal(document.getElementById('compressor-overview-state').textContent, '— rpm', 'Stale compressor speed is withheld from the tile');
assert.match(document.getElementById('monitoring-source-status').textContent, /stale/, 'The tile reports stale monitoring data');

qualityAwareData.monitoringQuality['FC4 block 184x4'] = freshEntry(['CompressorSpeed']);
qualityAwareData.systemControlQuality['FC4 register 195'].lastSuccessTimestamp = Date.now() - 301000;
window.handleMessage(qualityAwareData);
assert.equal(document.getElementById('heat-pump-mode').textContent, '—', 'Stale supply temperature cannot infer a heat pump mode');

const previousText = document.getElementById('heat-pump-mode').textContent;
assert.doesNotThrow(() => window.handleMessage('{malformed json'), 'Malformed JSON is ignored');
assert.equal(document.getElementById('heat-pump-mode').textContent, previousText, 'Malformed JSON does not replace visible state');

console.log('FWT tile: JSON messages, initial object payload, malformed input, and reported Off mode passed.');
