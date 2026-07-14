"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.runCCHQ = runCCHQ;
exports.createRuntime = createRuntime;
const lexer_1 = require("./lexer");
const parser_1 = require("./parser");
const environment_1 = require("./environment");
const executor_1 = require("./executor");
const builtins_1 = require("./builtins");
/**
 * CCHQCode Runtime 入口
 *
 * 执行 CCHQ 脚本，返回执行结果。
 * 这是一个纯函数，无副作用（Log 除外）。
 *
 * @param script  CCHQ 源码字符串
 * @param context 上下文数据对象（如 payload）
 * @returns       脚本执行结果
 *
 * @example
 * ```typescript
 * import { runCCHQ } from './tsjs/index';
 *
 * const result = runCCHQ(
 *   `@Regfunc<>Param:$payload&{
 *      @SetCallBackName("Main");
 *      @ReturnToBot("Hello, " + $payload);
 *    }
 *    @LifeStart(@RunFunc(Main, $payload))`,
 *   { payload: "World" }
 * );
 * console.log(result); // "Hello, World"
 * ```
 */
function runCCHQ(script, context = {}) {
    const env = new environment_1.Environment(context);
    (0, builtins_1.registerBuiltins)(env);
    const lexer = new lexer_1.Lexer(script);
    const tokens = lexer.tokenize();
    const parser = new parser_1.Parser(tokens);
    const ast = parser.parse();
    const executor = new executor_1.Executor(env, ast);
    return executor.run();
}
/**
 * 创建可复用的 Runtime 实例（适用于多次执行共享环境）
 */
function createRuntime(context = {}) {
    const env = new environment_1.Environment(context);
    (0, builtins_1.registerBuiltins)(env);
    return {
        /**
         * 执行一段 CCHQ 脚本
         */
        execute(script) {
            const lexer = new lexer_1.Lexer(script);
            const tokens = lexer.tokenize();
            const parser = new parser_1.Parser(tokens);
            const ast = parser.parse();
            const executor = new executor_1.Executor(env, ast);
            return executor.run();
        },
        /**
         * 注册自定义控件
         */
        registerControl(name, callable) {
            env.registerControl(name, callable);
        },
        /**
         * 获取运行时环境（调试用）
         */
        getEnvironment() {
            return env;
        }
    };
}
// 默认导出便捷函数
exports.default = runCCHQ;
