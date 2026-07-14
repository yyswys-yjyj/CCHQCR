import { ControlSignal } from './types';

/**
 * 运行时环境 - 管理作用域、变量、函数表、控件表
 */
export class Environment {
  private context: any;
  private scopeStack: Record<string, any>[] = [];
  private functions: Record<string, Record<number, any>> = {};
  private controls: Record<string, Function> = {};
  private lastRunFuncResult: any = null;

  constructor(context: any = {}) {
    this.context = context;
    this.pushScope();
    if (typeof context === 'object' && context !== null && !Array.isArray(context)) {
      for (const [key, value] of Object.entries(context)) {
        this.setVariable(key, value);
      }
    }
  }

  // ---------- 作用域 ----------
  pushScope(): void {
    this.scopeStack.push({});
  }
  popScope(): void {
    this.scopeStack.pop();
  }

  setVariable(name: string, value: any): void {
    // 如果变量在父作用域已存在，修改父作用域中的值
    for (let i = this.scopeStack.length - 2; i >= 0; i--) {
      if (name in this.scopeStack[i]) {
        this.scopeStack[i][name] = value;
        return;
      }
    }
    // 否则在当前作用域创建
    this.scopeStack[this.scopeStack.length - 1][name] = value;
  }

  getVariable(name: string): any {
    for (let i = this.scopeStack.length - 1; i >= 0; i--) {
      if (name in this.scopeStack[i]) {
        return this.scopeStack[i][name];
      }
    }
    // 回退到上下文
    if (typeof this.context === 'object' && this.context !== null && name in this.context) {
      return this.context[name];
    }
    throw new Error(`Undefined variable: ${name}`);
  }

  // ---------- 函数 ----------
  registerFunction(name: string, def: any, argCount: number): void {
    if (!this.functions[name]) {
      this.functions[name] = {};
    }
    this.functions[name][argCount] = def;
  }

  hasFunction(name: string, argCount: number): boolean {
    if (typeof name !== 'string') return false;
    return !!(this.functions[name] && this.functions[name][argCount]);
  }

  callFunction(name: string, args: any[]): any {
    if (typeof name !== 'string') {
      throw new Error(`Function name must be a string, got ${typeof name}`);
    }
    const argCount = args.length;
    if (!this.hasFunction(name, argCount)) {
      throw new Error(`Function ${name} with ${argCount} arguments not found`);
    }
    const def = this.functions[name][argCount];
    // 鸭子类型检查: 如果是函数定义节点（有 body 和 params）
    if (def && typeof def === 'object' && def.body && def.params) {
      this.pushScope();
      const params = def.params;
      const typeKeywords = ['any', 'bool', 'string', 'int', 'number', 'float', 'double', 'array', 'object', 'callable', 'void', 'null', 'mixed'];
      const isTypeParam = params.length === 1 && typeKeywords.includes(params[0]);

      if (isTypeParam) {
        this.setVariable('Param', args[0] ?? null);
      } else {
        this.setVariable('Param', {
          quantity: argCount,
          args: args
        });
      }
      for (let idx = 0; idx < params.length; idx++) {
        this.setVariable(params[idx], args[idx] ?? null);
      }
      const result = def.body.execute(this);
      this.popScope();
      return result;
    } else if (typeof def === 'function') {
      return def(...args);
    }
    throw new Error('Invalid function definition');
  }

  // ---------- 控件 ----------
  registerControl(name: string, callable: Function): void {
    this.controls[name] = callable;
  }
  hasControl(name: string): boolean {
    return name in this.controls;
  }
  callControl(name: string, args: any[]): any {
    if (!this.hasControl(name)) {
      throw new Error(`Control ${name} not registered`);
    }
    return this.controls[name](...args);
  }

  // ---------- 上下文 ----------
  getContext(): any { return this.context; }
  setContext(newCtx: any): void { this.context = newCtx; }

  resetScopes(): void {
    this.scopeStack = [];
    this.pushScope();
    if (typeof this.context === 'object' && this.context !== null && !Array.isArray(this.context)) {
      for (const [key, value] of Object.entries(this.context)) {
        this.setVariable(key, value);
      }
    } else {
      this.setVariable('payload', this.context);
    }
  }

  // ---------- RunFunc 结果 ----------
  setRunFuncResult(result: any): void { this.lastRunFuncResult = result; }
  getRunFuncResult(): any { return this.lastRunFuncResult; }
}
