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
        const result = this.program.execute(this.environment);
        if (result instanceof types_1.ControlSignal && result.type === 'return') {
            return result.value;
        }
        return result;
    }
}
exports.Executor = Executor;
