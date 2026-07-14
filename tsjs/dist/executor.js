"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.Executor = void 0;
const types_1 = require("./types");
/**
 * 执行器
 */
class Executor {
    constructor(env, program) {
        this.environment = env;
        this.program = program;
    }
    run() {
        while (true) {
            try {
                const result = this.program.execute(this.environment);
                if (result instanceof types_1.ControlSignal && result.type === 'return') {
                    return result.value;
                }
                return result;
            }
            catch (err) {
                if (err instanceof types_1.EventRestartSignal) {
                    this.environment.setContext(err.newPayload);
                    this.environment.resetScopes();
                    // 继续循环，重新执行
                }
                else {
                    throw err;
                }
            }
        }
    }
}
exports.Executor = Executor;
