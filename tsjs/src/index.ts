import { Lexer } from './lexer';
import { Parser } from './parser';
import { Environment } from './environment';
import { Executor } from './executor';
import { registerBuiltins } from './builtins';

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
export function runCCHQ(script: string, context: any = {}): any {
  const env = new Environment(context);
  registerBuiltins(env);

  const lexer = new Lexer(script);
  const tokens = lexer.tokenize();

  const parser = new Parser(tokens);
  const ast = parser.parse();

  const executor = new Executor(env, ast);
  return executor.run();
}

/**
 * 创建可复用的 Runtime 实例（适用于多次执行共享环境）
 */
export function createRuntime(context: any = {}) {
  const env = new Environment(context);
  registerBuiltins(env);

  return {
    /**
     * 执行一段 CCHQ 脚本
     */
    execute(script: string): any {
      const lexer = new Lexer(script);
      const tokens = lexer.tokenize();
      const parser = new Parser(tokens);
      const ast = parser.parse();
      const executor = new Executor(env, ast);
      return executor.run();
    },

    /**
     * 注册自定义控件
     */
    registerControl(name: string, callable: Function): void {
      env.registerControl(name, callable);
    },

    /**
     * 获取运行时环境（调试用）
     */
    getEnvironment(): Environment {
      return env;
    }
  };
}

// 默认导出便捷函数
export default runCCHQ;
