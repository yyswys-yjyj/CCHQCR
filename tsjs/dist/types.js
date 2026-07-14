"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.EventRestartSignal = exports.ControlSignal = void 0;
// ===== 控制信号 =====
class ControlSignal {
    constructor(type, value) {
        this.type = type;
        this.value = value;
    }
}
exports.ControlSignal = ControlSignal;
// ===== 事件重启异常 =====
class EventRestartSignal {
    constructor(payload) {
        this.newPayload = payload;
    }
}
exports.EventRestartSignal = EventRestartSignal;
