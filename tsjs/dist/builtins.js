"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.registerBuiltins = registerBuiltins;
const types_1 = require("./types");
/**
 * 内置函数注册
 */
function registerBuiltins(env) {
    // @GetEventInfo
    env.registerControl('GetEventInfo', function (data, path) {
        // 特殊处理 @GetEventInfo(RunFunc, result)
        if (data === 'RunFunc' && path === 'result') {
            return env.getRunFuncResult();
        }
        // 特殊处理 @GetEventInfo(Param, ...)
        if (data === 'Param') {
            const param = env.getVariable('Param');
            if (path === undefined)
                return param;
            if (path === 'quantity') {
                if (typeof param === 'object' && param !== null && 'quantity' in param) {
                    return param.quantity;
                }
                return 1; // 简单值的 Param 只有一个参数
            }
            if (typeof param === 'object' && param !== null && path in param) {
                return param[path];
            }
            return param;
        }
        // JSON 路径模式: @GetEventInfo(JSON->"path", $source)
        if (typeof data === 'object' && data !== null && data.__json_path__ === true) {
            const jsonStr = path;
            if (typeof jsonStr === 'string') {
                try {
                    const parsed = JSON.parse(jsonStr);
                    if (typeof parsed === 'object' && parsed !== null) {
                        const parts = data.path.split('.');
                        let current = parsed;
                        for (const key of parts) {
                            if (typeof current === 'object' && current !== null && key in current) {
                                current = current[key];
                            }
                            else {
                                return null;
                            }
                        }
                        return current;
                    }
                }
                catch {
                    return null;
                }
            }
            return null;
        }
        if (path === undefined)
            return data;
        // 支持点路径
        const parts = path.split('.');
        let current = data;
        for (const key of parts) {
            if (typeof current === 'object' && current !== null && key in current) {
                current = current[key];
            }
            else {
                return null;
            }
        }
        return current;
    });
    // @ReturnToBot
    env.registerControl('ReturnToBot', function (value) {
        return new types_1.ControlSignal('return', value);
    });
    // @Log
    env.registerControl('Log', function (message) {
        // 在控制台输出
        const msg = typeof message === 'object' ? JSON.stringify(message) : String(message);
        console.log('[CCHQ]', msg);
        return null;
    });
    // @SetCallBackName（运行时无需操作）
    env.registerControl('SetCallBackName', function (_name) {
        return null;
    });
}
